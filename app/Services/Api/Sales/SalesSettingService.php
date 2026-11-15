<?php

namespace App\Services\Api\Sales;

use App\Models\User\SalesSetting;
use App\Models\User\Workspace;
use App\Repositories\User\SalesSettingRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;

/**
 * Settings > Sales (Doc 11 section 15): the payment terms, validity, discounts,
 * notes and terms a new quotation, proforma invoice or invoice starts with.
 * Documents already made keep their own.
 */
class SalesSettingService
{
    private const FIELDS = [
        'payment_terms', 'quotation_validity', 'proforma_validity', 'line_discounts', 'document_discounts', 'notes', 'terms',
        'invoice_policy', 'customer_advances', 'credit_counts_orders', 'link_expiry_days', 'link_comments',
        'new_sales_start_as', 'default_price_list_id', 'deposit_percent', 'backorders', 'default_shipping_terms', 'salesperson_is_creator',
    ];

    /** The ones every page reads from the navigation (Doc 16 Phase H1): changed, its cached copies are not used again. */
    private const NAVIGATED = ['new_sales_start_as', 'deposit_percent', 'backorders'];

    public function __construct(
        protected SalesSettingRepository $salesSettingRepository,
        protected WorkspaceRepository $workspaceRepository,
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
        $changed  = array_keys(array_diff_assoc(
            array_map('strval', $settings->only(self::FIELDS)),
            array_map('strval', $before),
        ));

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry. The fields are those that differ from before -
        // from the defaults, the first time.
        ActivityLogService::record('updated', 'Updated the sales settings', $workspace, [
            'source'  => 'api',
            'fields'  => $changed,
            'changes' => SettingChanges::between($before, $settings->only(self::FIELDS)),
        ]);

        if (array_intersect($changed, self::NAVIGATED)) {
            $this->workspaceRepository->bumpNavVersion($workspace);
        }

        return $settings->load('defaultPriceList:id,name');
    }
}
