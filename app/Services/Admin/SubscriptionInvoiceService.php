<?php

namespace App\Services\Admin;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\User\User;
use App\Repositories\Billing\SubscriptionInvoiceRepository;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Billing\SubscriptionPdf;
use App\Services\Billing\SubscriptionRefunds;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * izSuite's subscription invoices in the admin panel (Doc 13 Phase C1):
 * every business's, the ones waiting for a bank transfer first. When the
 * money arrives, the admin confirms it - which issues the receipt and starts
 * the plan - or cancels an invoice that will not be paid.
 */
class SubscriptionInvoiceService
{
    public function __construct(
        protected SubscriptionInvoiceRepository $invoiceRepository,
        protected SubscriptionBilling $billing,
        protected SubscriptionPdf $pdf,
        protected SubscriptionRefunds $refunds,
    ) {}

    public function paginate(?string $status, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->invoiceRepository->adminPage($status, $search ?: null, $perPage);
    }

    /**
     * What an invoice's page shows (Doc 15 section 14 - Phase C2a): the
     * invoice with its payments and their refunds; what was paid of it -
     * payments applied to it - what izSuite refunded, and the balance still
     * due; and what is left to refund of each payment.
     *
     * @return array{invoice: SubscriptionInvoice, paid: string, refunded: string, balance: string, refundable: array<int, string>}
     */
    public function details(int $id): array
    {
        $invoice = $this->invoiceRepository->findForPage($id) ?? abort(404);
        $applied = $invoice->payments->filter(fn ($payment) => $payment->succeeded() && ! $payment->notApplied());
        $paid    = $applied->reduce(fn (string $sum, $payment) => bcadd($sum, (string) $payment->amount, 2), '0.00');

        return [
            'invoice'    => $invoice,
            'paid'       => $paid,
            'refunded'   => bcadd((string) ($invoice->refunded ?? '0'), '0', 2),
            'balance'    => $invoice->isOpen() ? bcsub((string) $invoice->total, $paid, 2) : '0.00',
            'refundable' => $invoice->payments->filter->succeeded()->mapWithKeys(fn ($payment) => [$payment->id => $this->refunds->refundable($payment)])->all(),
        ];
    }

    public function confirm(int $id, User $admin): SubscriptionInvoice
    {
        $invoice = $this->billing->confirm($this->invoiceRepository->findOrFail($id), $admin);

        // The admin's audit log (Doc 15 section 15.26); the business sees it in its billing history.
        ActivityLogService::record('confirmed', "Confirmed the payment of invoice {$invoice->number}", $invoice, ['workspace_id' => $invoice->workspace_id, 'total' => "{$invoice->currency} {$invoice->total}"], $admin);

        return $invoice;
    }

    public function void(int $id): SubscriptionInvoice
    {
        $invoice = $this->billing->void($this->invoiceRepository->findOrFail($id));

        ActivityLogService::record('cancelled', "Cancelled invoice {$invoice->number}", $invoice, ['workspace_id' => $invoice->workspace_id]);

        return $invoice;
    }

    /** @return array{0: string, 1: string} the invoice's PDF - or its receipt's - and its file name */
    public function pdf(int $id, bool $receipt): array
    {
        return $this->pdf->render($this->invoiceRepository->findOrFail($id), $receipt);
    }
}
