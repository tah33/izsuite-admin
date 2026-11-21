<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** A business's online storefronts (Doc 19 Phase A1), by name, each seen only where its location may be used. */
class StorefrontRepository
{
    /** What a storefront's row shows. */
    private const SHOWN_WITH = ['location:id,name', 'priceList:id,name'];

    /** Its storefronts at $locationIds - every one, for null - by name. */
    public function getFor(Workspace $workspace, ?array $locationIds): Collection
    {
        return $this->at($workspace, $locationIds)->with(self::SHOWN_WITH)->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?Storefront
    {
        return $this->at($workspace, $locationIds)->with(self::SHOWN_WITH)->find($id);
    }

    /** Whether another of the business's storefronts - $exceptId aside - is called $name. */
    public function nameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return Storefront::query()->where('workspace_id', $workspace->id)->where('name', $name)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** Whether any storefront of izSuite - $exceptId aside - has $code: it is a storefront's address. */
    public function codeTaken(string $code, ?int $exceptId = null): bool
    {
        return Storefront::query()->where('code', $code)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** Made for the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): Storefront
    {
        $storefront = new Storefront($data);
        $storefront->workspace_id = $workspace->id;
        $storefront->save();

        return $storefront->load(self::SHOWN_WITH);
    }

    /** Whether anything changed. */
    public function update(Storefront $storefront, array $data): bool
    {
        $storefront->update($data);
        $storefront->load(self::SHOWN_WITH);

        return $storefront->wasChanged();
    }

    public function delete(Storefront $storefront): void
    {
        $storefront->delete();
    }

    /** The storefront of izSuite whose code is $code - its izSuite address (Phase A3). */
    public function findByCode(string $code): ?Storefront
    {
        return Storefront::query()->where('code', $code)->first();
    }

    /** How many of the business's storefronts are not archived: those its plan counts (Phase A2). */
    public function countOpenFor(Workspace $workspace): int
    {
        return Storefront::query()->where('workspace_id', $workspace->id)->where('status', '!=', Storefront::ARCHIVED)->count();
    }

    /** Whether a storefront sells from $location: it then stays. */
    public function usesLocation(Location $location): bool
    {
        return Storefront::query()->where('location_id', $location->id)->exists();
    }

    private function at(Workspace $workspace, ?array $locationIds): Builder
    {
        return Storefront::query()->where('workspace_id', $workspace->id)->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds));
    }
}
