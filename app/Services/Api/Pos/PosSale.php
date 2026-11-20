<?php

namespace App\Services\Api\Pos;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Contact;
use App\Models\User\PosOrder;
use App\Models\User\PosOrderLine;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosRegister;
use App\Models\User\PosSession;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosReturnRepository;
use App\Repositories\User\PosSessionRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesPaymentRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Api\PaymentAccounts\AccountLedger;
use App\Services\Api\Sales\CreditNoteRefunds;
use App\Services\Api\Sales\InvoiceSettlement;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\User\Apps\WorkspaceApps;
use App\Services\User\Workspaces\WorkspaceSequence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A POS sale, recorded (Doc 18 sections 18.18 and 18.20 - Phase C2), all in
 * one transaction, in its session - still open once locked:
 *
 * - its invoice, issued at once: numbered by its register's own receipt run
 *   (its prefix and five digits, R1-00001, never reused), dated today, due
 *   today, at the register's location, in its currency, with the business,
 *   customer and template snapshots issuing writes, and made by Point of
 *   Sale (its source app) - so the Sales apps list it, and it counts once;
 * - its lines, as PosPricing priced them, and the POS order, keeping where
 *   each line's price came from, what was handed over and the change;
 * - each payment a customer payment, numbered as receipts are, into the
 *   account its method's money goes into, written to that account's
 *   history - unless its method waits to be confirmed: then it is pending
 *   (Phase D2) - and the invoice settled: paid, but for what is pending;
 * - an exchange's return paying it first (Phase E3): its credit note's
 *   credit applied to the invoice, the two linked, and what the credit
 *   leaves over paid back in cash.
 */
class PosSale
{
    use RefusesConflicts;

    public function __construct(
        protected PosSessionRepository $sessions,
        protected PosOrderRepository $orders,
        protected LocationRepository $locations,
        protected PaymentAccountRepository $accounts,
        protected SalesDocumentRepository $salesDocuments,
        protected SalesPaymentRepository $salesPayments,
        protected SalesSnapshots $snapshots,
        protected DocumentTemplates $templates,
        protected DocumentNumbers $documentNumbers,
        protected ExchangeRateService $exchangeRates,
        protected AccountLedger $ledger,
        protected InvoiceSettlement $settlement,
        protected WorkspaceSequence $sequence,
        protected PosCustomerCredit $credit,
        protected CreditNoteRefunds $refunds,
        protected PosReturnRepository $returns,
    ) {}

    /**
     * $sale: its lines (PosCounter::lines()), priced (PosPricing::priced()),
     * payments - checked, each its method, account_id, amount, tendered,
     * change and reference - the order's discount, why the discounts go
     * beyond the limit (Phase C3), its note, the rate and today - and what
     * its receipt ends with (Phase C4). The order, completed.
     */
    public function record(Workspace $workspace, User $user, PosSession $session, Contact $contact, array $sale): PosOrder
    {
        $register = $session->register;
        // The whole location, for the invoice's header (a snapshot of it).
        $location = $this->locations->findFor($workspace, $register->location_id);
        $appId    = WorkspaceApps::for($workspace)->row('pos')?->app_id;
        ['priced' => $priced, 'payments' => $payments, 'note' => $note, 'rate' => $rate, 'today' => $today] = $sale;
        $totals   = $priced['totals'];

        return DB::transaction(function () use ($workspace, $user, $session, $register, $location, $contact, $sale, $priced, $payments, $note, $rate, $today, $appId, $totals): PosOrder {
            if ($this->sessions->lock($session)->status !== PosSession::OPEN) {
                $this->refuse("Session {$session->number} is closed: open a session of your own to sell.");
            }

            $number  = $this->receiptNumber($workspace, $register);
            $invoice = $this->salesDocuments->createMadeBy($workspace, [
                'type'              => DocumentType::Invoice,
                'number'            => $number,
                'status'            => SalesDocumentStatus::Issued,
                'payment_status'    => bccomp($totals['grand_total'], '0', 2) > 0 ? SalesDocument::PAYMENT_UNPAID : 'paid',
                'contact_id'        => $contact->id,
                'location_id'       => $register->location_id,
                'document_date'     => $today,
                // Phase D3a: left partly on account, due by the customer's terms.
                'due_date'          => CarbonImmutable::parse($today)->addDays($sale['terms'] ?? 0)->toDateString(),
                'payment_terms'     => $sale['terms'] ?? 0,
                'currency'          => $register->currency,
                'exchange_rate'     => $rate,
                ...$this->exchangeRates->originOf($workspace, $register->currency, $today, $rate),
                'price_tax_mode'    => $workspace->price_tax_mode,
                'tax_rounding'      => $workspace->tax_rounding,
                // Phase C3: the order's discount, and why it went beyond the limit.
                'discount_type'     => $sale['discount']['type'] ?? null,
                'discount_value'    => $sale['discount']['value'] ?? null,
                'discount_reason'   => $sale['discount_reason'],
                'notes'             => $note,
                ...$totals,
                'balance_due'       => $totals['grand_total'],
                'issued_at'         => now(),
                'business_snapshot' => $this->snapshots->business($workspace, $location, $register->currency),
                'customer_snapshot' => $this->snapshots->customer($contact),
                'template_snapshot' => $this->templates->snapshot($workspace, DocumentType::Invoice),
                'created_by'        => $user->id,
                'updated_by'        => $user->id,
            ], $appId);

            $this->salesDocuments->replaceLines($invoice, array_map(fn (array $row) => [...$row, 'source_line_id' => null], $priced['rows']));

            $order = $this->orders->create($workspace, [
                'pos_session_id'    => $session->id,
                'pos_register_id'   => $register->id,
                'location_id'       => $register->location_id,
                'cashier_id'        => $user->id,
                'contact_id'        => $contact->id,
                'sales_document_id' => $invoice->id,
                'number'            => $number,
                'status'            => PosOrder::COMPLETED,
                'currency'          => $register->currency,
                'exchange_rate'     => $rate,
                'grand_total'       => $totals['grand_total'],
                'tendered'          => array_reduce($payments, fn (string $sum, array $payment) => bcadd($sum, $payment['tendered'], 2), '0.00'),
                'change_given'      => array_reduce($payments, fn (string $sum, array $payment) => bcadd($sum, $payment['change'], 2), '0.00'),
                'cash_rounding'     => $priced['rounding'],
                'notes'             => $note,
                'discount_reason'   => $sale['discount_reason'],
                'discount_approved_by' => $sale['discount_reason'] === null ? null : ($sale['discount_approver'] ?? $user->id),
                'receipt_texts'     => $sale['receipt_texts'] ?? null,
                'on_account'        => $sale['on_account'] ?? '0.00',
                'credit_reason'     => $sale['credit_reason'] ?? null,
                'credit_applied'    => $sale['credit'] ?? '0.00',
                'completed_at'      => now(),
            ]);

            // The item lines - the Cash rounding line, last, has none - each with where its price came from, or the price an override replaced and why.
            $lineIds = array_slice($this->salesDocuments->lineIds($invoice), 0, count($priced['prices']));

            $this->orders->addLines($order, array_map(fn (int $lineId, array $price, array $line) => [
                'sales_document_line_id' => $lineId,
                'price_source'           => $price['source'],
                'standard_price'         => $price['source'] === PosOrderLine::OVERRIDE ? $price['standard'] : null,
                'override_reason'        => $line['override_reason'],
                // Phase I2: the manager who approved it at the register.
                'override_approved_by'   => $price['source'] === PosOrderLine::OVERRIDE ? ($sale['override_approver'] ?? null) : null,
            ], $lineIds, $priced['prices'], $sale['lines']));

            $balance = $totals['grand_total'];

            // Phase D3b: the customer's own credit, applied first - no payment, nothing into an account.
            if (bccomp($sale['credit'] ?? '0', '0', 2) > 0) {
                $this->credit->apply($workspace, $user, $invoice, $sale['credit'], $today);
                $balance = bcsub($balance, $sale['credit'], 2);
            }

            // Phase E3: an exchange's return pays first, from its credit note; what it leaves over is paid back in cash.
            if ($exchange = $sale['exchange'] ?? null) {
                $this->exchange($workspace, $user, $order, $invoice, $exchange, $today);
                $balance = bcsub($balance, $exchange['applied'], 2);
            }

            foreach ($payments as $payment) {
                // Phase D2: one that waits to be confirmed pays nothing yet.
                if (! $payment['method']->confirms_later) {
                    $balance = bcsub($balance, $payment['amount'], 2);
                }

                $this->pay($workspace, $user, $order, $invoice, $register, $payment, $balance, $rate, $today, $appId);
            }

            $this->settlement->settle($invoice);

            return $order;
        });
    }

    /**
     * One of the sale's payments: a customer payment into its account, in its
     * history - or, by a method that waits to be confirmed, pending: none yet
     * (Phase D2) - and the order's record of it.
     */
    private function pay(Workspace $workspace, User $user, PosOrder $order, SalesDocument $invoice, PosRegister $register, array $payment, string $balance, string $rate, string $today, ?int $appId): void
    {
        $pending  = (bool) $payment['method']->confirms_later;
        $received = $pending ? null : $this->receive($workspace, $user, $invoice, $register->location_id, $payment['account_id'], [
            'method' => $payment['method']->type, 'reference' => $payment['reference'], 'amount' => $payment['amount'],
        ], $balance, $register->currency, $rate, $today, $appId);

        $this->orders->addPayment($order, [
            'pos_payment_method_id' => $payment['method']->id,
            'method_name'           => $payment['method']->name,
            'method_type'           => $payment['method']->type,
            'payment_account_id'    => $payment['account_id'],
            'sales_payment_id'      => $received?->id,
            'amount'                => $payment['amount'],
            'tendered'              => $payment['tendered'],
            'change_given'          => $payment['change'],
            'reference'             => $payment['reference'],
            'status'                => $pending ? PosOrderPayment::PENDING : PosOrderPayment::COMPLETED,
        ]);
    }

    /**
     * A customer payment of $invoice into the account $accountId, numbered as
     * receipts are, in the account's history: a sale's payment - or a pending
     * one, confirmed (Phase D2). $paid is its method, reference and amount, in
     * $currency at $rate; $balance what the invoice owes after it.
     */
    public function receive(Workspace $workspace, User $user, SalesDocument $invoice, int $locationId, int $accountId, array $paid, string $balance, string $currency, string $rate, string $today, ?int $appId): SalesPayment
    {
        $account = $this->accounts->findFor($workspace, $accountId);

        $this->ledger->checkMove($account, $today, $today);

        $received = $this->salesPayments->createMadeBy($workspace, [
            'kind'               => SalesPayment::PAYMENT,
            'number'             => $this->documentNumbers->next(
                $workspace,
                DocumentType::CustomerReceipt,
                CarbonImmutable::parse($today),
                fn (string $number) => $this->salesPayments->receiptNumberTaken($workspace, $number),
                $locationId,
            ),
            'sales_document_id'  => $invoice->id,
            'contact_id'         => $invoice->contact_id,
            'payment_account_id' => $account->id,
            'location_id'        => $locationId,
            'payment_date'       => $today,
            'method'             => $paid['method'],
            'reference'          => $paid['reference'],
            'amount'             => $paid['amount'],
            'applied_amount'     => $paid['amount'],
            'exchange_rate'      => '1',
            'base_amount'        => $this->ledger->baseAmount($workspace, $paid['amount'], $account->currency, $paid['amount'], $currency, $today, $rate),
            'balance_after'      => $balance,
            'template_snapshot'  => $this->templates->snapshot($workspace, DocumentType::CustomerReceipt),
            'created_by'         => $user->id,
        ], $appId);

        $this->ledger->recordPayment($workspace, $received);

        return $received;
    }

    /**
     * An exchange's return paying the sale of its replacement (Phase E3): its
     * credit note's credit applied to the sale's invoice, the two linked, and
     * what the credit leaves over paid back in cash from the register.
     */
    private function exchange(Workspace $workspace, User $user, PosOrder $order, SalesDocument $invoice, array $exchange, string $today): void
    {
        // Locked: one sale only uses an exchange.
        $return = $this->returns->lock($exchange['return']);

        if ($return->exchange_order_id !== null) {
            $this->refuse("{$return->number}'s exchange was sold already.");
        }

        if (bccomp($exchange['applied'], '0', 2) > 0) {
            $this->credit->applyNote($workspace, $user, $invoice, $return->creditNote, $exchange['applied'], $today);
        }

        $refunded = '0.00';

        if (bccomp($exchange['payback'], '0', 2) > 0) {
            $this->refunds->refund($workspace, $return->credit_note_id, $user, [
                'payment_account_id' => $exchange['cash']['account_id'], 'payment_date' => $today, 'amount' => $exchange['payback'], 'method' => $exchange['cash']['type'],
            ]);
            $refunded = $exchange['payback'];
        }

        $this->orders->update($order, ['exchange_return_id' => $return->id, 'exchange_credit' => $exchange['applied'], 'exchange_refunded' => $refunded]);
        $this->returns->update($return, ['exchange_order_id' => $order->id]);
    }

    /** The register's next receipt number - its prefix and five digits - skipping one an invoice of the business has already. */
    private function receiptNumber(Workspace $workspace, PosRegister $register): string
    {
        do {
            $number = $register->receipt_prefix.sprintf('%05d', $this->sequence->next($workspace->id, "pos_receipt|{$register->id}"));
        } while ($this->salesDocuments->numberTaken($workspace, DocumentType::Invoice, $number));

        return $number;
    }
}
