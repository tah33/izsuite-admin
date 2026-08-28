<?php

namespace App\Repositories\Billing;

use App\Models\Billing\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository
{
    /**
     * Get paginated invoices for a user.
     */
    public function getForUser(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Invoice::where('user_id', $userId)
            ->with('subscription')
            ->latest()
            ->paginate(requested_per_page($perPage));
    }

    /**
     * Find an invoice by ID for a user.
     */
    public function findForUser(int $userId, int $invoiceId): ?Invoice
    {
        return Invoice::where('user_id', $userId)
            ->with('subscription')
            ->find($invoiceId);
    }

    /**
     * Create a new invoice.
     */
    public function create(array $data): Invoice
    {
        $data['invoice_number'] = $data['invoice_number'] ?? Invoice::generateNumber();

        return Invoice::create($data);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);

        return $invoice->fresh();
    }

    public function latestForSubscription(int $subscriptionId): ?Invoice
    {
        return Invoice::where('subscription_id', $subscriptionId)
            ->latest('id')
            ->first();
    }

    public function markPendingPaidForSubscription(int $subscriptionId, string $paymentId): int
    {
        return Invoice::where('subscription_id', $subscriptionId)
            ->where('status', 'pending')
            ->update([
                'status'     => 'paid',
                'paid_at'    => now(),
                'payment_id' => $paymentId,
            ]);
    }

    public function cancelPendingForSubscription(int $subscriptionId): int
    {
        return Invoice::where('subscription_id', $subscriptionId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Mark an invoice as paid.
     */
    public function markPaid(Invoice $invoice, ?string $paymentId = null): Invoice
    {
        $invoice->update([
            'status'     => 'paid',
            'paid_at'    => now(),
            'payment_id' => $paymentId ?? $invoice->payment_id,
        ]);

        return $invoice->fresh();
    }

    /**
     * Get total revenue for a user.
     */
    public function getTotalPaid(int $userId): float
    {
        return (float) Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->sum('amount');
    }
}
