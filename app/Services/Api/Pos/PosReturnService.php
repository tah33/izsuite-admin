<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosOrder;
use App\Models\User\PosOrderLine;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosReturn;
use App\Models\User\PosSession;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosReturnRepository;
use App\Repositories\User\PosSettingRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Api\Sales\CreditNoteLines;
use App\Services\Api\Sales\CreditNoteRefunds;
use App\Services\Api\Sales\CreditNoteService;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentPdf;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Workspaces\WorkspaceSequence;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POS returns and their refunds (Doc 18 sections 18.23 and 18.24 - Phase
 * E1), made from the original sale - found by its receipt number - in the
 * cashier's own open session, by whoever may process returns.
 *
 * Each item comes back at most as much as is still returnable - what the
 * sale's credit notes have not taken - at the sale's prices, discounts and
 * taxes, with its condition, and why. Underneath it is the Sales app's credit
 * note of the sale's invoice, issued at once, so it counts once (Doc 08
 * section 15).
 *
 * Its refund - for whoever may process refunds - is paid out of the
 * register's cash, or by the sale's own payment method into its account,
 * never more than the sale was paid in money (SalesDocumentRules::
 * refundable); what is not paid out stays as the customer's credit, or
 * takes off what the invoice still owes. Left as credit by choice, it needs a
 * named customer. Each return is logged on its credit note.
 *
 * Phase E2: paid back by another of the register's methods - for whoever may
 * use alternative refund methods, saying why. By a method that waits to be
 * confirmed, the refund waits as pending (PosPendingRefunds): nothing is paid
 * out until someone who may confirms it.
 *
 * Phase E3: made as an exchange, nothing is paid out: its credit pays the
 * sale of the replacement, which follows (PosExchanges) - for any customer,
 * the walk-in one too.
 */
class PosReturnService
{
    use RefusesConflicts;

    public function __construct(
        protected PosCounter $counter,
        protected PosOrderRepository $orders,
        protected PosReturnRepository $returns,
        protected SalesDocumentRepository $salesDocuments,
        protected CreditNoteLines $creditNoteLines,
        protected CreditNoteService $creditNotes,
        protected CreditNoteRefunds $refunds,
        protected SalesDocumentRules $rules,
        protected PosSettingRepository $settings,
        protected WorkspaceSequence $sequence,
        protected WorkspaceAccess $access,
        protected DocumentPdf $documentPdf,
        protected PosApprovals $approvals,
    ) {}

    /**
     * The sale whose receipt is $number, as a return starts from it: each of
     * its items with what is still returnable, and how it was paid.
     *
     * @return array{order: PosOrder, invoice: SalesDocument, lines: list<array>, walk_in: bool}
     */
    public function lookup(Workspace $workspace, string $number): array
    {
        $this->counter->selling($workspace);

        $order = $this->orders->findByNumber($workspace, trim($number), $this->access->locations())
            ?? abort(404, 'There is no sale with the receipt number '.trim($number).'.');

        return $this->returnable($workspace, $order);
    }

    /** $data is validated (see QuotePosReturnRequest): the sale and the lines. What the return credits: its total and taxes. */
    public function quote(Workspace $workspace, array $data): array
    {
        $this->counter->selling($workspace);

        ['invoice' => $invoice] = $this->returnable($workspace, $this->order($workspace, (int) $data['order_id']));
        [$totals] = $this->creditNoteLines->build($invoice, $this->requested($invoice, $data['lines']));

        return ['total' => $totals['grand_total'], 'tax_total' => $totals['tax_total']];
    }

    /** $data is validated (see RecordPosReturnRequest): the sale, the lines, why, notes and how it is refunded. The return. */
    public function record(Workspace $workspace, array $data): PosReturn
    {
        $session = $this->counter->selling($workspace);
        $order   = $this->order($workspace, (int) $data['order_id']);
        ['invoice' => $invoice, 'walk_in' => $walkIn] = $this->returnable($workspace, $order);
        $method  = $data['refund'];
        $refund  = match ($method) {
            PosReturn::CREDIT, PosReturn::EXCHANGE => null,
            PosReturn::METHOD => $this->alternative($workspace, $session, $data),
            default           => $this->refundTo($workspace, $session, $order, $method),
        };
        $user    = $this->access->user();
        $today   = $this->counter->today($workspace);
        $lines   = $this->requested($invoice, $data['lines']);

        if ($method === PosReturn::CREDIT && $walkIn) {
            throw ValidationException::withMessages(['refund' => 'A walk-in customer is refunded in cash, or by the sale\'s own method: their credit cannot be kept for them.']);
        }

        if ($refund !== null) {
            abort_unless($this->access->can('pos.returns.refund'), 403, 'Paying back a return needs Process Refunds: leave it as the customer\'s credit instead.');
        }

        $return = DB::transaction(function () use ($workspace, $session, $order, $invoice, $data, $method, $refund, $user, $today, $lines): PosReturn {
            $note = $this->creditNotes->create($workspace, $invoice->id, $user, [
                'document_date' => $today, 'reason' => trim($data['reason']), 'notes' => trim((string) ($data['notes'] ?? '')) ?: null, 'lines' => $lines,
            ]);
            $note = $this->creditNotes->issue($workspace, $note->id, $user);

            // Paid out: never more than the sale was paid in money - the rest stays credit, or takes off what the invoice owes.
            $out     = $refund === null ? '0.00' : $this->rules->refundable($note->refresh());
            $waits   = ($refund['waits'] ?? false) && bccomp($out, '0', 2) > 0;
            $payment = bccomp($out, '0', 2) > 0 && ! $waits ? $this->refunds->refund($workspace, $note->id, $user, [
                'payment_account_id' => $refund['account_id'], 'payment_date' => $today, 'amount' => $out, 'method' => $refund['type'],
            ]) : null;

            $return = $this->returns->create($workspace, [
                'number'             => 'RT-'.sprintf('%05d', $this->sequence->next($workspace->id, 'pos_return')),
                'pos_order_id'       => $order->id,
                'pos_session_id'     => $session->id,
                'pos_register_id'    => $session->pos_register_id,
                'location_id'        => $session->register->location_id,
                'cashier_id'         => $user->id,
                'contact_id'         => $order->contact_id,
                'credit_note_id'     => $note->id,
                'reason'             => trim($data['reason']),
                'notes'              => trim((string) ($data['notes'] ?? '')) ?: null,
                'currency'           => $order->currency,
                'total'              => $note->grand_total,
                'refund_method'      => $method,
                'refund_method_name' => $refund['name'] ?? null,
                'refund_method_type' => $refund['type'] ?? null,
                'refund_payment_id'  => $payment?->id,
                'refunded'           => $payment ? $out : '0.00',
                // Phase E2: why another method paid it back; one that waits, what it will pay out, and from where.
                'refund_status'      => $waits ? PosReturn::PENDING : PosReturn::COMPLETED,
                'refund_reason'      => $refund['reason'] ?? null,
                'refund_approved_by' => $refund['approver_id'] ?? null,
                'refund_account_id'  => $refund['account_id'] ?? null,
                'refund_due'         => $waits ? $out : '0.00',
            ]);

            $this->returns->addLines($return, array_map(fn (array $line, array $asked) => [
                'sales_document_line_id' => $line['source_line_id'],
                'quantity'               => $line['quantity'],
                'condition'              => $asked['condition'],
                'amount'                 => Decimal::round(bcmul((string) $line['quantity'], (string) $line['unit_price'], 10)),
            ], $lines, array_values($data['lines'])));

            return $return;
        });

        $kept = bcsub(bcsub((string) $return->total, (string) $return->refunded, 2), (string) $return->refund_due, 2);

        ActivityLogService::record('created', "Took back {$return->number} from {$order->number} at the register \"{$session->register->name}\" ({$session->register->code}), for {$order->currency} {$return->total}: "
            .match (true) {
                bccomp((string) $return->refunded, '0', 2) > 0  => "{$return->refund_method_name} {$return->refunded}",
                bccomp((string) $return->refund_due, '0', 2) > 0 => "{$return->refund_method_name} {$return->refund_due} (pending)",
                default                                        => 'nothing paid back',
            }
            .($return->refund_reason ? " - by it because {$return->refund_reason}" : '')
            .(bccomp($kept, '0', 2) > 0 ? ", {$order->currency} {$kept} ".($method === PosReturn::EXCHANGE ? 'kept for the exchange' : 'credited') : '')." - {$return->reason}", $return->creditNote, ['source' => 'api']);

        return $this->returns->loadShown($return);
    }

    /** @return array{0: string, 1: string} a return's refund receipt (Phase E2): its file name and PDF */
    public function receipt(Workspace $workspace, int $id): array
    {
        $return = $this->returns->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such return.');

        return $this->documentPdf->posRefundReceipt($workspace, $this->returns->loadForReceipt($return));
    }

    /**
     * $order's invoice - issued - and each of its items with what is still
     * returnable, and whether it was sold to the walk-in customer.
     */
    private function returnable(Workspace $workspace, PosOrder $order): array
    {
        $invoice = $this->salesDocuments->findIssuedInvoiceFor($workspace, (int) $order->sales_document_id)
            ?? $this->refuse("{$order->number}'s invoice is no longer issued: nothing of it can be returned here.");
        $left    = $this->creditNoteLines->remaining($invoice);

        return [
            'order'   => $order,
            'invoice' => $invoice,
            'lines'   => $order->lines->map(fn (PosOrderLine $line) => $left[$line->sales_document_line_id] ?? null)->filter()->values()->all(),
            'walk_in' => $order->contact_id === $this->settings->forWorkspace($workspace)->walk_in_contact_id,
        ];
    }

    /** The lines asked - each a line of the sale and a quantity - as the credit note takes them, at the sale's own prices. */
    private function requested(SalesDocument $invoice, array $lines): array
    {
        $own = $invoice->lines->keyBy('id');

        return array_map(function (array $line, int $index) use ($own): array {
            $sold = $own->get((int) $line['line_id']) ?? throw ValidationException::withMessages(["lines.{$index}.line_id" => 'This line is not one of the sale\'s.']);

            return ['source_line_id' => $sold->id, 'quantity' => bcadd((string) $line['quantity'], '0', 3), 'unit_price' => (string) $sold->unit_price];
        }, array_values($lines), array_keys(array_values($lines)));
    }

    /**
     * Where the refund is paid out of: the register's cash - its own cash
     * account, else its cash method's - or the sale's own first method that
     * paid it, into the account it went into.
     *
     * @return array{account_id: int, name: string, type: string}
     */
    private function refundTo(Workspace $workspace, PosSession $session, PosOrder $order, string $method): array
    {
        if ($method === PosReturn::CASH) {
            $cash = $this->counter->methods($workspace, $session->register)->first(fn (array $taken) => $taken['method']->gives_change);

            return $cash ? ['account_id' => $cash['account_id'], 'name' => $cash['method']->name, 'type' => $cash['method']->type]
                : $this->refuse('This register takes no cash: refund by the sale\'s own method, or leave it as the customer\'s credit.');
        }

        $paid = $order->payments->first(fn (PosOrderPayment $payment) => $payment->status === PosOrderPayment::COMPLETED && $payment->payment_account_id !== null);

        // Phase E2: by a method that waits to be confirmed, it waits too.
        return $paid ? ['account_id' => $paid->payment_account_id, 'name' => $paid->method_name, 'type' => $paid->method_type, 'waits' => (bool) $paid->method?->confirms_later]
            : $this->refuse("{$order->number} was not paid by a method that can pay back: refund in cash, or leave it as the customer's credit.");
    }

    /**
     * Another of the register's methods paying it back (Phase E2): for whoever
     * may use alternative refund methods, one the register takes now, and why.
     *
     * @return array{account_id: int, name: string, type: string, waits: bool, reason: string}
     */
    private function alternative(Workspace $workspace, PosSession $session, array $data): array
    {
        // Phase I2: or a manager who may approves it at the register.
        $approver = $this->access->can('pos.returns.alternative') ? null : $this->approvals->approverFor($workspace, 'refund', $data['approvals']['refund'] ?? null);
        abort_unless($this->access->can('pos.returns.alternative') || $approver, 403, 'Paying back by another method needs Use Alternative Refund Methods - or a manager\'s approval at the register.');

        $taken  = $this->counter->methods($workspace, $session->register)->get((int) ($data['method_id'] ?? 0))
            ?? throw ValidationException::withMessages(['method_id' => 'Choose one of the payment methods this register takes.']);
        $reason = trim((string) ($data['refund_reason'] ?? ''))
            ?: throw ValidationException::withMessages(['refund_reason' => 'Say why it is paid back this way.']);

        return ['account_id' => $taken['account_id'], 'name' => $taken['method']->name, 'type' => $taken['method']->type, 'waits' => (bool) $taken['method']->confirms_later, 'reason' => $reason, 'approver_id' => $approver?->id];
    }

    /** One of the business's completed sales, at a location the user may use. */
    private function order(Workspace $workspace, int $id): PosOrder
    {
        return $this->orders->findForReturn($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such sale.');
    }
}
