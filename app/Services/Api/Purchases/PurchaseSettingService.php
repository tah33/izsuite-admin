<?php

namespace App\Services\Api\Purchases;

use App\Models\User\PurchaseSetting;
use App\Models\User\Workspace;
use App\Repositories\User\PurchaseSettingRepository;
use App\Services\Shared\ActivityLogService;

/**
 * Settings > Purchases (Doc 11 section 16): the payment terms a supplier
 * without their own has, the notes a new purchase starts with, and whether
 * recording one checks for a supplier invoice entered before. Purchases
 * already made keep their own.
 */
class PurchaseSettingService
{
    private const FIELDS = ['payment_terms', 'notes', 'duplicate_check'];

    public function __construct(
        protected PurchaseSettingRepository $purchaseSettingRepository,
    ) {}

    public function settings(Workspace $workspace): PurchaseSetting
    {
        return $this->purchaseSettingRepository->forWorkspace($workspace);
    }

    /** $data is validated (see SavePurchaseSettingsRequest). */
    public function save(Workspace $workspace, array $data): PurchaseSetting
    {
        $before   = $this->settings($workspace)->only(self::FIELDS);
        $settings = $this->purchaseSettingRepository->save($workspace, $data);

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry - with the fields that differ from before.
        ActivityLogService::record('updated', 'Updated the purchase settings', $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_diff_assoc(
                array_map('strval', $settings->only(self::FIELDS)),
                array_map('strval', $before),
            )),
        ]);

        return $settings;
    }
}
