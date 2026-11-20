<?php

namespace App\Repositories\User;

use App\Models\User\PosCashMovement;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** POS cash movements (Doc 18 Phase F1). */
class PosCashMovementRepository
{
    public function create(Workspace $workspace, array $data): PosCashMovement
    {
        $movement = new PosCashMovement($data);
        $movement->workspace_id = $workspace->id;
        $movement->save();

        return $movement;
    }

    /**
     * The Cash Movements page: the business's cash movements, by kind and status - and by who recorded them, for the cashier - at $locationIds - any, when null - the latest first, by
     * register, session and cashier (Doc 18 Phase G1).
     */
    public function paginateFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        return PosCashMovement::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->when($filters['register_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_register_id', (int) $id))
            ->when($filters['session_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('pos_session_id', (int) $id))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('created_by', (int) $id))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['register:id,name,code', 'session:id,number', 'account:id,name', 'expense:id,number', 'creator'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** One of the business's cash movements, at one of $locationIds - any, when null - with its session; or null. */
    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?PosCashMovement
    {
        return PosCashMovement::query()->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))
            ->with('session')
            ->find($id);
    }

    public function lock(PosCashMovement $movement): PosCashMovement
    {
        return PosCashMovement::query()->lockForUpdate()->findOrFail($movement->id);
    }

    public function update(PosCashMovement $movement, array $data): void
    {
        $movement->forceFill($data)->save();
    }

    /** The session's cash movements, newest first, each with its other account and who recorded it. */
    public function of(PosSession $session): Collection
    {
        return PosCashMovement::query()->where('pos_session_id', $session->id)
            ->with(['account:id,name', 'expense:id,number', 'creator'])
            ->orderByDesc('id')
            ->get();
    }

    /** @return array{in: string, out: string} what the session's movements put into its drawer and took out of it - those that moved */
    public function totalsIn(PosSession $session): array
    {
        $moved = fn (string $direction) => Decimal::round((string) PosCashMovement::query()->where('pos_session_id', $session->id)
            ->where('status', PosCashMovement::COMPLETED)->where('direction', $direction)->sum('amount'));

        return ['in' => $moved(PosCashMovement::IN), 'out' => $moved(PosCashMovement::OUT)];
    }

    /** The session's first cash movement waiting for approval, or null. */
    public function firstPendingIn(PosSession $session): ?PosCashMovement
    {
        return PosCashMovement::query()->where('pos_session_id', $session->id)->where('status', PosCashMovement::PENDING)->orderBy('id')->first();
    }

    public function loadShown(PosCashMovement $movement): PosCashMovement
    {
        return $movement->load(['account:id,name', 'expense:id,number', 'creator']);
    }

    /** The movement read again as it stands now, as loadShown() loads it. */
    public function reload(PosCashMovement $movement): PosCashMovement
    {
        return $this->loadShown($movement->refresh());
    }
}
