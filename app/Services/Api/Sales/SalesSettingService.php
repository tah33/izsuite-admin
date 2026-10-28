<?php

namespace App\Services\Api\Sales;

use App\Models\User\SalesSetting;
use App\Models\User\Workspace;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Shared\ActivityLogService;

/**
 * Settings > Sales (Doc 11 section 15): the payment terms, validity, discounts,
 * notes and terms a new quotation, proforma invoice or invoice starts with.
 * Documents already made keep their own.
 */
class SalesSettingService
{
    private const FIELDS = ['payment_terms', 'quotation_validity', 'proforma_validity', 'line_discounts', 'document_discounts', 'notes', 'terms'];

    public function __construct(
        protected SalesSettingRepository $salesSettingRepository,
    ) {}

    public function settings(Workspace $workspace): SalesSetting
    {
        return $this->salesSettingRepository->forWorkspace($workspace);
    }

    /** $data is validated (see SaveSalesSettingsRequest). */
    public function save(Workspace $workspace, array $data): SalesSetting
    {
        $before   = $this->settings($workspace)->only(self::FIELDS);
        $settings = $this->salesSettingRepository->save($workspace, $data);

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry. The fields are those that differ from before -
        // from the defaults, the first time.
        ActivityLogService::record('updated', 'Updated the sales settings', $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_diff_assoc(
                array_map('strval', $settings->only(self::FIELDS)),
                array_map('strval', $before),
            )),
        ]);

        return $settings;
    }
}
