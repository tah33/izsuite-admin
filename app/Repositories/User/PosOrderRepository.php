<?php

namespace App\Repositories\User;

use App\Models\User\PosOrder;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** POS orders (Doc 18 Phase C2): each made for its business, with its payments and what its lines' prices came from. */
class PosOrderRepository
{
    /** Made for the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): PosOrder
    {
        $order = new PosOrder($data);
        $order->workspace_id = $workspace->id;
        $order->save();

        return $order;
    }

    public function addLines(PosOrder $order, array $rows): void
    {
        $order->lines()->createMany($rows);
    }

    public function addPayment(PosOrder $order, array $data): PosOrderPayment
    {
        return $order->payments()->create($data);
    }

    /** With what its answer shows: its payments, and its customer's e-mail address for the receipt (Phase C4). */
    /**
     * The Orders page: the business's sales, each with what returns credited of it, at $locationIds - any, when null - the latest first, by
     * register, session and cashier (Doc 18 Phase G1).
     */
    public function paginateFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        return PosOrder::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->when($filters['register_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_register_id', (int) $id))
            ->when($filters['session_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_session_id', (int) $id))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('cashier_id', (int) $id))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', fn (Builder $query) => $query->where('number', 'like', '%'.trim($filters['search']).'%'))
            ->withSum('returns', 'total')
            ->with(['register:id,name,code', 'session:id,number', 'cashier:id,first_name,last_name', 'contact:id,name', 'payments', 'invoice:id,number'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function loadShown(PosOrder $order): PosOrder
    {
        return $order->load(['payments', 'invoice:id,number', 'contact:id,email', 'exchangeReturn:id,number']);
    }

    /** One of the business's orders, at one of $locationIds - any, when null - or null. */
    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?PosOrder
    {
        return PosOrder::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->find($id);
    }

    /** One of the business's completed sales by its receipt number, at one of $locationIds - any, when null - with what a return needs (Phase E1); or null. */
    public function findByNumber(Workspace $workspace, string $number, ?array $locationIds): ?PosOrder
    {
        return $this->returnable($workspace, $locationIds)->where('number', $number)->first();
    }

    /** As findByNumber(), by its id. */
    public function findForReturn(Workspace $workspace, int $id, ?array $locationIds): ?PosOrder
    {
        return $this->returnable($workspace, $locationIds)->find($id);
    }

    /** With what its receipt shows (Phase C4): its invoice and the invoice's lines, register, cashier, customer and payments. */
    public function loadForReceipt(PosOrder $order): PosOrder
    {
        return $order->load([
            'invoice', 'invoice.location', 'register:id,name,code,location_id', 'cashier:id,first_name,last_name', 'contact:id,name,email',
            'payments', 'lines.invoiceLine',
        ]);
    }

    /** The order, read again and held until the transaction ends: two copies of its receipt are never counted as one. */
    public function lock(PosOrder $order): PosOrder
    {
        return PosOrder::query()->lockForUpdate()->findOrFail($order->id);
    }

    public function update(PosOrder $order, array $data): void
    {
        $order->update($data);
    }

    /** The session's last completed sale - its number and how many copies of its receipt were made - or null. */
    public function lastIn(PosSession $session): ?PosOrder
    {
        return PosOrder::query()->where('pos_session_id', $session->id)->where('status', PosOrder::COMPLETED)
            ->with('invoice:id,number')
            ->latest('id')->first(['id', 'number', 'receipt_prints', 'contact_id', 'sales_document_id', 'invoiced_at']);
    }

    /** The session's completed orders, each with its total and its invoice's tax: what it has sold. */
    public function completedIn(PosSession $session): Collection
    {
        return PosOrder::query()->where('pos_session_id', $session->id)->where('status', PosOrder::COMPLETED)
            ->with('invoice:id,tax_total')
            ->orderBy('id')
            ->get(['id', 'sales_document_id', 'grand_total']);
    }

    /** What the session's exchanges paid back in cash - the difference a replacement left of its return's credit (Phase E3). */
    public function exchangeRefundedIn(PosSession $session): string
    {
        return Decimal::round((string) PosOrder::query()->where('pos_session_id', $session->id)->where('status', PosOrder::COMPLETED)->sum('exchange_refunded'));
    }

    /** The payments of the session's completed orders, in the order they were taken - each pending, completed or declined (Phase D2). */
    public function paymentsIn(PosSession $session): Collection
    {
        return PosOrderPayment::query()
            ->whereHas('order', fn (Builder $query) => $query->where('pos_session_id', $session->id)->where('status', PosOrder::COMPLETED))
            ->orderBy('id')
            ->get(['id', 'pos_order_id', 'method_name', 'method_type', 'amount', 'status']);
    }

    /** The session's payments waiting to be confirmed (Phase D2), each with its order, in the order they were taken. */
    public function pendingPaymentsIn(PosSession $session): Collection
    {
        return $this->pendingIn($session)->with('order:id,number,currency,completed_at')->orderBy('id')->get();
    }

    public function countPendingIn(PosSession $session): int
    {
        return $this->pendingIn($session)->count();
    }

    /** One of the business's order payments, its order at one of $locationIds - any, when null - with the order and its invoice; or null. */
    public function findPayment(Workspace $workspace, int $id, ?array $locationIds): ?PosOrderPayment
    {
        return PosOrderPayment::query()
            ->whereHas('order', fn (Builder $query) => $query->where('workspace_id', $workspace->id)
                ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds)))
            ->with('order.invoice')
            ->find($id);
    }

    /** The payment as it stands, held until the transaction ends: it is confirmed or declined once. */
    public function lockPayment(PosOrderPayment $payment): PosOrderPayment
    {
        return PosOrderPayment::query()->lockForUpdate()->findOrFail($payment->id);
    }

    public function updatePayment(PosOrderPayment $payment, array $data): void
    {
        $payment->update($data);
    }

    private function returnable(Workspace $workspace, ?array $locationIds): Builder
    {
        return PosOrder::query()->where('workspace_id', $workspace->id)->where('status', PosOrder::COMPLETED)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->with(['lines', 'payments', 'contact:id,name']);
    }

    private function pendingIn(PosSession $session): Builder
    {
        return PosOrderPayment::query()->where('status', PosOrderPayment::PENDING)
            ->whereHas('order', fn (Builder $query) => $query->where('pos_session_id', $session->id)->where('status', PosOrder::COMPLETED));
    }
}
