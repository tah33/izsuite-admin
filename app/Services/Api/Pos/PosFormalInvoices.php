<?php

namespace App\Services\Api\Pos;

use App\Enums\DocumentType;
use App\Models\User\PosOrder;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosSettingRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentPdf;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A POS sale's formal invoice (Doc 18 section 18.18 - Phase D4): created
 * from the same sale, never a second one. The sale's own invoice - numbered
 * by its register's receipt run - is given the business's invoice number,
 * once, at its location, in its period; the order keeps the receipt number,
 * and the invoice names it. For a named customer, not the walk-in one; by
 * its cashier, or whoever may reprint receipts (PosOrderAccess). Its PDF is
 * the business's A4 or Letter invoice.
 */
class PosFormalInvoices
{
    use RefusesConflicts;

    public function __construct(
        protected PosOrderAccess $orderAccess,
        protected PosOrderRepository $orders,
        protected SalesDocumentRepository $salesDocuments,
        protected PosSettingRepository $settings,
        protected DocumentNumbers $numbers,
        protected DocumentPdf $documentPdf,
        protected RowLockRepository $rows,
    ) {}

    /** The order, its invoice numbered as the business's invoices are. */
    public function issue(Workspace $workspace, int $id): PosOrder
    {
        $order = $this->orderAccess->find($workspace, $id);

        $this->orderAccess->mayHandle($order);

        if ($order->contact_id === $this->settings->forWorkspace($workspace)->walk_in_contact_id) {
            $this->refuse("A formal invoice is made out to a named customer: {$order->number} was sold to the walk-in customer.");
        }

        DB::transaction(function () use ($workspace, $order): void {
            if ($this->orders->lock($order)->invoiced_at !== null) {
                $this->refuse("{$order->number} has its formal invoice already: {$order->invoice->number}.");
            }

            $invoice = $this->rows->lock($order->invoice);
            $number  = $this->numbers->next(
                $workspace,
                DocumentType::Invoice,
                CarbonImmutable::parse($invoice->document_date),
                fn (string $number) => $this->salesDocuments->numberTaken($workspace, DocumentType::Invoice, $number),
                $invoice->location_id,
            );

            $this->salesDocuments->update($invoice, ['number' => $number]);
            $this->orders->update($order, ['invoiced_at' => now()]);
        });

        $order->load('invoice:id,number');

        ActivityLogService::record('updated', "Gave the POS sale {$order->number} its formal invoice number {$order->invoice->number}", $order->invoice, ['source' => 'api']);

        return $order;
    }

    /** @return array{0: string, 1: string} the formal invoice's file name and PDF */
    public function pdf(Workspace $workspace, int $id): array
    {
        $order = $this->orderAccess->find($workspace, $id);

        $this->orderAccess->mayHandle($order);

        if ($order->invoiced_at === null) {
            $this->refuse("{$order->number} has no formal invoice yet.");
        }

        return $this->documentPdf->sales($workspace, $this->salesDocuments->loadForPdf($order->invoice));
    }
}
