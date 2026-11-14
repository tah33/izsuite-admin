<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Sales\SalesOrderProgress;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Validation\ValidationException;

/**
 * Invoicing a sales order (Doc 16 sections 16.11 and 16.12). Each line may be
 * invoiced up to its ordered quantity - or its delivered one, where Settings →
 * Sales or its item says so; a service always its ordered one - less what its
 * issued invoices billed. Draft invoices count only once issued, but what they
 * hold is shown, so the next invoice does not take it twice.
 *
 * Billing beyond that needs "Invoice Beyond the Order" and a reason, kept on
 * the invoice; issuing checks again. The order's lines count what issued
 * invoices billed and their issued credit notes credited, worked out again
 * from them whenever one is issued or voided (refresh()), and its invoice
 * status follows.
 */
class SalesOrderInvoicing
{
    use RefusesConflicts;

    public const BEYOND = 'sales.orders.invoice_beyond';

    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesSettingRepository $salesSettingRepository,
        protected SalesOrderProgress $salesOrderProgress,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * Each line of $order, by its id: what may be invoiced of it, what issued
     * invoices billed, what draft invoices hold, and what is left.
     *
     * @return array<int, array{allowed: string, invoiced: string, drafted: string, left: string}>
     */
    public function lines(Workspace $workspace, SalesDocument $order): array
    {
        $policy  = $this->salesSettingRepository->forWorkspace($workspace)->invoice_policy ?? 'ordered';
        $drafted = $this->salesDocumentRepository->quantitiesFrom($order->lines->pluck('id')->all(), DocumentType::Invoice, draft: true);
        $rows    = [];

        $this->salesDocumentRepository->loadLineItems($order);

        foreach ($order->lines as $line) {
            $allowed = $this->allowed($line, $policy);
            $held    = $drafted[$line->id] ?? '0';
            $left    = bcsub(bcsub($allowed, (string) $line->invoiced_quantity, 3), $held, 3);

            $rows[$line->id] = [
                'allowed'  => $allowed,
                'invoiced' => (string) $line->invoiced_quantity,
                'drafted'  => bcadd($held, '0', 3),
                'left'     => bccomp($left, '0', 3) > 0 ? $left : '0.000',
            ];
        }

        return $rows;
    }

    /**
     * $quantities - order line id => quantity, above zero - checked against
     * what each line has left: beyond it needs the permission and $reason.
     * Whether it goes beyond.
     */
    public function check(Workspace $workspace, SalesDocument $order, array $quantities, ?string $reason): bool
    {
        $lines  = $this->lines($workspace, $order);
        $beyond = collect($quantities)->contains(fn ($quantity, $id) => isset($lines[$id]) && bccomp((string) $quantity, $lines[$id]['left'], 3) > 0);

        if ($beyond && ! $this->access->can(self::BEYOND)) {
            throw ValidationException::withMessages(['lines' => 'That is more than the order has left to invoice. Invoicing beyond it needs "Invoice Beyond the Order".']);
        }

        if ($beyond && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Say why it bills more than the order has left.']);
        }

        return $beyond;
    }

    /**
     * Before a draft invoice made from an order is issued: what it bills, and
     * what other issued invoices billed since, must still fit each line - or
     * the invoice says why it may not, and the user may invoice beyond.
     */
    public function checkIssue(Workspace $workspace, SalesDocument $invoice): void
    {
        $order = $this->orderOf($invoice);

        if (! $order) {
            return;
        }

        $policy = $this->salesSettingRepository->forWorkspace($workspace)->invoice_policy ?? 'ordered';
        $lines  = $this->salesDocumentRepository->loadLineItems($order)->lines->keyBy('id');
        $billed = [];

        foreach ($invoice->lines as $line) {
            if ($line->source_line_id && $lines->has($line->source_line_id)) {
                $billed[$line->source_line_id] = bcadd($billed[$line->source_line_id] ?? '0', (string) $line->quantity, 3);
            }
        }

        $beyond = collect($billed)->contains(fn (string $quantity, int $id) => bccomp(
            bcadd((string) $lines[$id]->invoiced_quantity, $quantity, 3),
            $this->allowed($lines[$id], $policy),
            3,
        ) > 0);

        if ($beyond && (blank($invoice->over_invoice_reason) || ! $this->access->can(self::BEYOND))) {
            $this->refuse("This invoice bills more than {$order->number} has left to invoice. Lower it, or say why in its form - invoicing beyond the order needs \"Invoice Beyond the Order\".");
        }
    }

    /** The sales order a document comes from: an invoice's, or a credit note's invoice's. */
    public function orderOf(SalesDocument $document): ?SalesDocument
    {
        $source = $document->type === DocumentType::CreditNote ? $document->source?->source : $document->source;

        return $source?->type === DocumentType::SalesOrder ? $source : null;
    }

    /**
     * $order's lines' invoiced and credited quantities, worked out again from
     * its issued invoices and their issued credit notes, and its invoice
     * status with them.
     */
    public function refresh(SalesDocument $order): void
    {
        $ids      = $order->lines->pluck('id')->all();
        $invoiced = $this->salesDocumentRepository->quantitiesFrom($ids, DocumentType::Invoice);
        $credited = $this->salesDocumentRepository->creditedFrom($ids);

        foreach ($order->lines as $line) {
            $this->salesDocumentRepository->updateLine($line, [
                'invoiced_quantity' => $invoiced[$line->id] ?? '0',
                'credited_quantity' => $credited[$line->id] ?? '0',
            ]);
        }

        $this->salesDocumentRepository->update($order, ['invoice_status' => $this->salesOrderProgress->invoiceStatus($order)]);
    }

    /** What may be invoiced of a line: its delivered quantity where its policy says so and it is to be delivered, else its ordered one. */
    private function allowed(SalesDocumentLine $line, string $policy): string
    {
        $own = $line->item?->invoice_policy ?? $policy;

        return bcadd($own === 'delivered' && $this->salesOrderProgress->needsDelivery($line) ? (string) $line->delivered_quantity : (string) $line->quantity, '0', 3);
    }
}
