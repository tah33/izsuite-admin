<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Location;
use App\Models\User\ShippingMethod;
use App\Models\User\ShippingZone;
use App\Models\User\Workspace;
use App\Repositories\User\ShippingRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Validation\ValidationException;

/**
 * Settings → eCommerce's shipping and pickup (Doc 19 sections 19.26 and
 * 19.37 - Phase D1), under Manage Shipping:
 *
 * - zones, each a name of its own in the business, its countries, and the
 *   regions, cities and postal codes it is narrowed to - in an order, as
 *   given or after the last;
 * - a zone's methods (ShippingMethodRules checks them), in its order;
 * - pickup: each of the business's locations switched on or off for its
 *   customers to collect from, with its instructions and when an order is
 *   ready - an inactive location is not switched on.
 *
 * Each change is logged. A zone deleted takes its methods with it; orders
 * (Phase D4) keep what they were shipped by.
 */
class ShippingSettingService
{
    public function __construct(
        protected ShippingRepository $shipping,
        protected ShippingMethodRules $methodRules,
    ) {}

    /**
     * The zones with their methods, the locations as pickup points, and what
     * a method chooses from: the sales tax rates - none while the business
     * has taxes off - and the item categories, with the base currency its
     * prices are in.
     *
     * @return array{zones: \Illuminate\Database\Eloquent\Collection, pickup: \Illuminate\Database\Eloquent\Collection, tax_rates: ?\Illuminate\Database\Eloquent\Collection, categories: \Illuminate\Database\Eloquent\Collection, currency: string}
     */
    public function show(Workspace $workspace): array
    {
        return [
            'zones'      => $this->shipping->zones($workspace),
            'pickup'     => $this->shipping->pickupLocations($workspace),
            'tax_rates'  => $workspace->usesTaxes() ? $this->shipping->salesTaxRates($workspace) : null,
            'categories' => $this->shipping->categories($workspace),
            'currency'   => $workspace->base_currency,
        ];
    }

    public function addZone(Workspace $workspace, array $data): ShippingZone
    {
        $zone = $this->shipping->createZone($workspace, [
            ...$this->checkedZone($workspace, $data),
            'position' => $data['position'] ?? $this->shipping->nextZonePosition($workspace),
        ]);

        ActivityLogService::record('created', "Added the shipping zone \"{$zone->name}\"", $zone, ['source' => 'api']);

        return $zone;
    }

    public function updateZone(Workspace $workspace, int $id, array $data): ShippingZone
    {
        $zone = $this->findZone($workspace, $id);

        $this->shipping->updateZone($zone, [
            ...$this->checkedZone($workspace, $data, $zone),
            ...(isset($data['position']) ? ['position' => $data['position']] : []),
        ]);

        ActivityLogService::record('updated', "Updated the shipping zone \"{$zone->name}\"", $zone, ['source' => 'api', 'changes' => SettingChanges::of($zone)]);

        return $zone;
    }

    public function deleteZone(Workspace $workspace, int $id): void
    {
        $zone = $this->findZone($workspace, $id);

        $this->shipping->deleteZone($zone);

        ActivityLogService::record('deleted', "Deleted the shipping zone \"{$zone->name}\" and its methods", $zone, ['source' => 'api']);
    }

    public function addMethod(Workspace $workspace, array $data): ShippingMethod
    {
        $zone   = $this->findZone($workspace, (int) $data['shipping_zone_id']);
        $method = $this->shipping->createMethod($workspace, [
            ...$this->methodRules->checked($workspace, $zone, $data),
            'position' => $data['position'] ?? $this->shipping->nextMethodPosition($zone),
        ]);

        ActivityLogService::record('created', "Added the shipping method \"{$method->name}\" to {$zone->name}", $method, ['source' => 'api']);

        return $method;
    }

    public function updateMethod(Workspace $workspace, int $id, array $data): ShippingMethod
    {
        $method = $this->shipping->findMethod($workspace, $id) ?? abort(404, 'There is no such shipping method.');
        $zone   = $this->findZone($workspace, (int) $data['shipping_zone_id']);

        $this->shipping->updateMethod($method, [
            ...$this->methodRules->checked($workspace, $zone, $data, $method),
            ...(isset($data['position']) ? ['position' => $data['position']] : []),
        ]);

        ActivityLogService::record('updated', "Updated the shipping method \"{$method->name}\" of {$zone->name}", $method, ['source' => 'api', 'changes' => SettingChanges::of($method)]);

        return $method;
    }

    public function deleteMethod(Workspace $workspace, int $id): void
    {
        $method = $this->shipping->findMethod($workspace, $id) ?? abort(404, 'There is no such shipping method.');

        $this->shipping->deleteMethod($method);

        ActivityLogService::record('deleted', "Deleted the shipping method \"{$method->name}\"", $method, ['source' => 'api']);
    }

    /** $location switched on or off for pickup, with its instructions and when an order is ready there. */
    public function savePickup(Workspace $workspace, int $locationId, array $data): Location
    {
        $location = $this->shipping->findLocation($workspace, $locationId) ?? abort(404, 'There is no such location.');
        $on       = (bool) $data['is_active'];

        if ($on && ! $location->is_active) {
            throw ValidationException::withMessages(['is_active' => "{$location->name} is inactive: make it active in Settings → Locations first."]);
        }

        $point = $this->shipping->savePickup($workspace, $location, [
            'is_active'    => $on,
            'instructions' => $this->text($data['instructions'] ?? null),
            'ready_within' => $this->text($data['ready_within'] ?? null),
        ]);

        ActivityLogService::record(
            $point->wasRecentlyCreated ? 'created' : 'updated',
            ($on ? 'Offered pickup at ' : 'Stopped offering pickup at ').$location->name,
            $point,
            ['source' => 'api', ...($point->wasRecentlyCreated ? [] : ['changes' => SettingChanges::of($point)])],
        );

        return $location->setRelation('pickupPoint', $point);
    }

    private function findZone(Workspace $workspace, int $id): ShippingZone
    {
        return $this->shipping->findZone($workspace, $id) ?? abort(404, 'There is no such shipping zone.');
    }

    /**
     * $data as a zone keeps it: its name its own in the business, its
     * countries once each, and its regions, cities and postal codes once
     * each - postal codes in capitals - each list none when empty.
     */
    private function checkedZone(Workspace $workspace, array $data, ?ShippingZone $zone = null): array
    {
        $name = trim($data['name']);

        if ($this->shipping->zoneNameTaken($workspace, $name, $zone?->id)) {
            throw ValidationException::withMessages(['name' => 'Another shipping zone has this name.']);
        }

        return [
            'name'         => $name,
            'countries'    => array_values(array_unique($data['countries'])),
            'regions'      => $this->places($data['regions'] ?? []),
            'cities'       => $this->places($data['cities'] ?? []),
            'postal_codes' => $this->places(array_map(fn (?string $code) => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $code))), $data['postal_codes'] ?? [])),
        ];
    }

    /** $names trimmed, each once whatever its case, in the order given - none when there are none. */
    private function places(array $names): ?array
    {
        $kept = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name !== '' && ! isset($kept[mb_strtolower($name)])) {
                $kept[mb_strtolower($name)] = $name;
            }
        }

        return $kept === [] ? null : array_values($kept);
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
