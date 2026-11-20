<?php

namespace App\Repositories\User;

use App\Models\User\PosHeldOrder;
use App\Models\User\PosRegister;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Orders held at POS registers (Doc 18 Phase D1): each made for its business. */
class PosHeldOrderRepository
{
    /** What a held order's row names: who held it, and for whom. */
    private const LISTED_WITH = ['cashier:id,first_name,last_name', 'contact:id,name'];

    /** Made for the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): PosHeldOrder
    {
        $held = new PosHeldOrder($data);
        $held->workspace_id = $workspace->id;
        $held->save();

        return $held->load(self::LISTED_WITH);
    }

    /** One of the business's held orders, at one of $locationIds - any, when null - or null. */
    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?PosHeldOrder
    {
        return PosHeldOrder::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->with(['register:id,name,code', ...self::LISTED_WITH])
            ->find($id);
    }

    /** The orders held at $register still waiting - held, not past their time - the latest first. */
    public function waitingAt(PosRegister $register): Collection
    {
        return $this->waiting(PosHeldOrder::query()->where('pos_register_id', $register->id))
            ->with(self::LISTED_WITH)
            ->latest('id')
            ->get();
    }

    public function countWaitingAt(PosRegister $register): int
    {
        return $this->waiting(PosHeldOrder::query()->where('pos_register_id', $register->id))->count();
    }

    /** The held order as it stands, held until the transaction ends: it is resumed or cancelled once. */
    public function lock(PosHeldOrder $held): PosHeldOrder
    {
        return PosHeldOrder::query()->lockForUpdate()->findOrFail($held->id);
    }

    public function update(PosHeldOrder $held, array $data): void
    {
        $held->update($data);
    }

    /** Every business's orders still held past their time, with their registers: to expire. */
    public function pastTheirTime(): Collection
    {
        return PosHeldOrder::query()->where('status', PosHeldOrder::HELD)->where('expires_at', '<=', now())
            ->with('register:id,name,code')
            ->orderBy('id')
            ->get();
    }

    private function waiting(Builder $query): Builder
    {
        return $query->where('status', PosHeldOrder::HELD)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
