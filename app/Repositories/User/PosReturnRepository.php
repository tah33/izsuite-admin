<?php

namespace App\Repositories\User;

use App\Models\User\PosPaymentMethod;
use App\Models\User\PosReturn;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** POS returns (Doc 18 Phase E1): each made for its business, with its lines. */
class PosReturnRepository
{
    /** Made for the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): PosReturn
    {
        $return = new PosReturn($data);
        $return->workspace_id = $workspace->id;
        $return->save();

        return $return;
    }

    public function addLines(PosReturn $return, array $rows): void
    {
        $return->lines()->createMany($rows);
    }

    /** What the session's returns paid back in cash - from its drawer. */
    public function cashRefundedIn(PosSession $session): string
    {
        return Decimal::round((string) PosReturn::query()->where('pos_session_id', $session->id)
            ->where('refund_method_type', PosPaymentMethod::CASH)
            ->sum('refunded'));
    }

    /**
     * The Returns and Refunds page: the business's returns, by how each was paid back and its refund's status, or a number searched for - its own or its sale's - at $locationIds - any, when null - the latest first, by
     * register, session and cashier (Doc 18 Phase G1).
     */
    public function paginateFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        return PosReturn::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->when($filters['register_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_register_id', (int) $id))
            ->when($filters['session_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_session_id', (int) $id))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('cashier_id', (int) $id))
            ->when($filters['refund'] ?? null, fn (Builder $query, string $method) => $query->where('refund_method', $method))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('refund_status', $status))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', function (Builder $query) use ($filters) {
                $like = '%'.trim($filters['search']).'%';
                $query->where(fn (Builder $query) => $query->where('number', 'like', $like)->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', $like)));
            })
            ->with(['order:id,number', 'register:id,name,code', 'session:id,number', 'cashier:id,first_name,last_name', 'contact:id,name', 'exchangeOrder:id,number'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** One of the business's returns, at one of $locationIds - any, when null - with its sale's number and its credit note; or null. */
    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?PosReturn
    {
        return PosReturn::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->with(['order:id,number', 'creditNote'])
            ->find($id);
    }

    /** The session's returns whose refund waits to be confirmed (Phase E2), each with its sale's number, in the order they were made. */
    public function pendingIn(PosSession $session): Collection
    {
        return PosReturn::query()->where('pos_session_id', $session->id)->where('refund_status', PosReturn::PENDING)
            ->with('order:id,number')
            ->orderBy('id')
            ->get();
    }

    /** The return as it stands, held until the transaction ends: its refund is confirmed or declined once. */
    public function lock(PosReturn $return): PosReturn
    {
        return PosReturn::query()->lockForUpdate()->findOrFail($return->id);
    }

    public function update(PosReturn $return, array $data): void
    {
        $return->update($data);
    }

    /** With what its refund receipt shows (Phase E2): its credit note's lines, its sale's number, register, cashier and customer. */
    public function loadForReceipt(PosReturn $return): PosReturn
    {
        return $return->load(['creditNote.lines', 'order:id,number', 'register:id,name,code', 'cashier:id,first_name,last_name', 'contact:id,name']);
    }

    /** With what its answer shows: its credit note's number. */
    public function loadShown(PosReturn $return): PosReturn
    {
        return $return->load(['creditNote:id,number,balance_due', 'contact:id,name']);
    }
}
