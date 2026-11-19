<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\PosPaymentMethod;
use App\Models\User\PosRegister;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** A business's POS registers (Doc 18 Phase A2), by name, each seen only where its location may be used. */
class PosRegisterRepository
{
    /** What a register's row shows. */
    private const SHOWN_WITH = [
        'location:id,name', 'priceList:id,name', 'cashAccount:id,name',
        'paymentMethods:pos_payment_methods.id,name', 'cashiers:users.id,first_name,last_name',
    ];

    /** Its registers at $locationIds - every one, for null - by name. */
    public function getFor(Workspace $workspace, ?array $locationIds): Collection
    {
        return $this->at($workspace, $locationIds)->with(self::SHOWN_WITH)->withExists('sessions')->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?PosRegister
    {
        return $this->at($workspace, $locationIds)->with(self::SHOWN_WITH)->withExists('sessions')->find($id);
    }

    /** The register with its cashiers, locked while a session opens on it: inside a transaction. */
    public function lockFor(Workspace $workspace, int $id): ?PosRegister
    {
        return PosRegister::query()->where('workspace_id', $workspace->id)->lockForUpdate()->with('cashiers:users.id')->find($id);
    }

    /** Whether another of its registers - $exceptId aside - has $value as its $column (name or code). */
    public function taken(Workspace $workspace, string $column, string $value, ?int $exceptId = null): bool
    {
        return PosRegister::query()->where('workspace_id', $workspace->id)->where($column, $value)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /**
     * Made for the business, so its workspace is never read from $data,
     * taking $methodIds and sold at by $cashierIds.
     */
    public function create(Workspace $workspace, array $data, array $methodIds, array $cashierIds): PosRegister
    {
        $register = new PosRegister($data);
        $register->workspace_id = $workspace->id;
        $register->save();

        $register->paymentMethods()->sync($methodIds);
        $register->cashiers()->sync($cashierIds);

        return $register->load(self::SHOWN_WITH)->loadExists('sessions');
    }

    /** Its fields, methods and cashiers; whether any of them changed. */
    public function update(PosRegister $register, array $data, array $methodIds, array $cashierIds): bool
    {
        $register->update($data);

        $methods  = $register->paymentMethods()->sync($methodIds);
        $cashiers = $register->cashiers()->sync($cashierIds);

        $register->load(self::SHOWN_WITH);

        return $register->wasChanged() || array_filter($methods) !== [] || array_filter($cashiers) !== [];
    }

    public function delete(PosRegister $register): void
    {
        $register->delete();
    }

    /** Whether a register belongs to $location: it then stays. */
    public function usesLocation(Location $location): bool
    {
        return PosRegister::query()->where('location_id', $location->id)->exists();
    }

    /** The registers that take $method, by name: it is taken off them before it is deleted. */
    public function takingMethod(PosPaymentMethod $method): Collection
    {
        return PosRegister::query()
            ->whereHas('paymentMethods', fn (Builder $query) => $query->whereKey($method->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function at(Workspace $workspace, ?array $locationIds): Builder
    {
        return PosRegister::query()->where('workspace_id', $workspace->id)->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds));
    }
}
