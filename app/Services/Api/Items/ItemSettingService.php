<?php

namespace App\Services\Api\Items;

use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Settings > Items (Doc 11 section 18, Doc 04 section 7): whether the business
 * uses Brand Management and the warranty fields, its own units of measure and
 * the one new items start with, whether new items are given a SKU - and its
 * prefix - and the format barcodes are checked against.
 *
 * It lives on the workspace. Brands and warranties ride in the sidebar's
 * payload, so a change to them bumps its nav_version (see Workspace).
 * Turning either off hides them; what was saved in them is kept. A unit an
 * item has stays on the list.
 */
class ItemSettingService
{
    private const FIELDS = ['brands_enabled', 'warranty_enabled', 'default_unit', 'units', 'sku_auto', 'sku_prefix', 'barcode_format'];

    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected ItemRepository $itemRepository,
    ) {}

    public function settings(Workspace $workspace): array
    {
        $units = $workspace->units ?? [];

        return [
            'brands_enabled'   => (bool) $workspace->brands_enabled,
            'warranty_enabled' => (bool) $workspace->warranty_enabled,
            'default_unit'     => $workspace->default_unit,
            'units'            => $units,
            // The business's own units that items have: they stay on the list.
            'units_in_use'     => $this->itemRepository->unitsUsed($workspace, $units),
            'sku_auto'         => (bool) $workspace->sku_auto,
            'sku_prefix'       => $workspace->sku_prefix,
            'barcode_format'   => $workspace->barcode_format,
        ];
    }

    /** $data is validated (see SaveItemSettingsRequest); what it leaves out stays as it is. */
    public function save(Workspace $workspace, array $data): array
    {
        $data = Arr::only($data, self::FIELDS);

        if (array_key_exists('units', $data)) {
            $kept = $this->itemRepository->unitsUsed($workspace, array_diff($workspace->units ?? [], $data['units']));

            if ($kept) {
                throw ValidationException::withMessages(['units' => "Items are measured in \"{$kept[0]}\", so it stays on the list."]);
            }
        }

        $this->workspaceRepository->forceUpdate($workspace, $data);

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry. Brands switched on their own read as that.
        $description = array_keys($data) === ['brands_enabled']
            ? ($data['brands_enabled'] ? 'Turned on' : 'Turned off').' Brand Management'
            : 'Updated the item settings';

        ActivityLogService::record('updated', $description, $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_intersect_key($workspace->getChanges(), array_flip(self::FIELDS))),
        ]);

        return $this->settings($workspace);
    }
}
