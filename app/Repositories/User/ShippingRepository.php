<?php

namespace App\Repositories\User;

use App\Models\User\Location;
use App\Models\User\PickupPoint;
use App\Models\User\ShippingMethod;
use App\Models\User\ShippingZone;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shipping and pickup (Doc 19 Phase D1): the business's zones in their order,
 * each with its methods, and its locations with whether customers may collect
 * from them - as Settings → eCommerce edits them, and as a checkout offers
 * them (those on).
 */
class ShippingRepository
{
    /** What a method's row names besides itself: its tax. */
    private const METHOD_WITH = ['taxRate:id,name,rate'];

    /** Its zones in their order, each with its methods in theirs. */
    public function zones(Workspace $workspace): Collection
    {
        return $workspace->shippingZones()->with(['methods', 'methods.taxRate:id,name,rate'])->orderBy('position')->orderBy('id')->get();
    }

    /** Its zones in their order, each with the methods that are on - what a checkout offers from. */
    public function offeredZones(Workspace $workspace): Collection
    {
        return $workspace->shippingZones()
            ->with(['methods' => fn (HasMany $methods) => $methods->where('is_active', true)])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function findZone(Workspace $workspace, int $id): ?ShippingZone
    {
        return $workspace->shippingZones()->with(['methods', 'methods.taxRate:id,name,rate'])->find($id);
    }

    /** Whether another of its zones - $exceptId aside - is called $name. */
    public function zoneNameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return $workspace->shippingZones()->where('name', $name)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** The place after its last zone. */
    public function nextZonePosition(Workspace $workspace): int
    {
        return (int) $workspace->shippingZones()->max('position') + 1;
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function createZone(Workspace $workspace, array $data): ShippingZone
    {
        return $workspace->shippingZones()->create($data)->load(['methods', 'methods.taxRate:id,name,rate']);
    }

    public function updateZone(ShippingZone $zone, array $data): ShippingZone
    {
        $zone->update($data);

        return $zone->load(['methods', 'methods.taxRate:id,name,rate']);
    }

    /** Its methods go with it. */
    public function deleteZone(ShippingZone $zone): void
    {
        $zone->delete();
    }

    public function findMethod(Workspace $workspace, int $id): ?ShippingMethod
    {
        return $workspace->shippingMethods()->with(self::METHOD_WITH)->find($id);
    }

    /** Whether another method of $zone - $exceptId aside - is called $name. */
    public function methodNameTaken(ShippingZone $zone, string $name, ?int $exceptId = null): bool
    {
        return $zone->methods()->where('name', $name)->when($exceptId, fn (Builder $query, int $id) => $query->whereKeyNot($id))->exists();
    }

    /** The place after $zone's last method. */
    public function nextMethodPosition(ShippingZone $zone): int
    {
        return (int) $zone->methods()->max('position') + 1;
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function createMethod(Workspace $workspace, array $data): ShippingMethod
    {
        return $workspace->shippingMethods()->create($data)->load(self::METHOD_WITH);
    }

    public function updateMethod(ShippingMethod $method, array $data): ShippingMethod
    {
        $method->update($data);

        return $method->load(self::METHOD_WITH);
    }

    public function deleteMethod(ShippingMethod $method): void
    {
        $method->delete();
    }

    /**
     * Its locations as pickup points: the active ones, and any inactive one
     * still switched on for pickup - each with its pickup settings, if it
     * has any - the primary one first, then by name.
     */
    public function pickupLocations(Workspace $workspace): Collection
    {
        return $workspace->locations()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereHas('pickupPoint', fn (Builder $point) => $point->where('is_active', true)))
            ->with('pickupPoint')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'name', 'is_primary', 'is_active', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'phone']);
    }

    public function findLocation(Workspace $workspace, int $id): ?Location
    {
        return $workspace->locations()->with('pickupPoint')->find($id);
    }

    /** $location's pickup settings, made or changed. */
    public function savePickup(Workspace $workspace, Location $location, array $data): PickupPoint
    {
        return $workspace->pickupPoints()->updateOrCreate(['location_id' => $location->id], $data);
    }

    /** The pickup points on, at active locations - what a checkout offers - with their locations, the primary one first. */
    public function offeredPickupPoints(Workspace $workspace): Collection
    {
        return $workspace->pickupPoints()
            ->where('pickup_points.is_active', true)
            ->whereHas('location', fn (Builder $location) => $location->where('is_active', true))
            ->with('location:id,name,is_primary,address_line_1,address_line_2,city,state,postal_code,country,phone')
            ->get()
            ->sortBy(fn (PickupPoint $point) => [$point->location->is_primary ? 0 : 1, $point->location->name])
            ->values();
    }

    /** The sales tax rates a method may take: active, for sales. */
    public function salesTaxRates(Workspace $workspace): Collection
    {
        return $workspace->taxRates()->where('is_active', true)->where('for_sales', true)->orderBy('name')->get(['id', 'name', 'rate']);
    }

    /** Whether $id is one of the sales tax rates a method may take. */
    public function isSalesTaxRate(Workspace $workspace, int $id): bool
    {
        return $workspace->taxRates()->whereKey($id)->where('is_active', true)->where('for_sales', true)->exists();
    }

    /** Its item categories by name, for a method to carry. */
    public function categories(Workspace $workspace): Collection
    {
        return $workspace->itemCategories()->orderBy('name')->get(['id', 'name']);
    }

    /** Which of $ids are its item categories. */
    public function categoryIds(Workspace $workspace, array $ids): array
    {
        return $workspace->itemCategories()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
