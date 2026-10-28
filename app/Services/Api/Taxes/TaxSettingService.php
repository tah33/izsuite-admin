<?php

namespace App\Services\Api\Taxes;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\TaxRateRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Settings > Taxes (Doc 01 section 12, Doc 11 sections 9 and 11): whether the
 * business uses taxes - yes, no, or decide later - and its tax options: whether
 * prices include tax, how tax is rounded, and its default sales and purchase
 * tax. The rates themselves are TaxRateService's. Withholding tax is switched
 * on apart from the rest (section 9), and its rates are WithholdingTaxService's.
 *
 * Turning taxes off hides tax fields; it never deletes or changes what is
 * recorded in them. The choice lives on the workspace, and changing it bumps
 * nav_version (see Workspace), because the sidebar's payload carries it.
 */
class TaxSettingService
{
    private const DEFAULT_KEYS = [
        TaxRate::SALES     => 'default_sales_tax_id',
        TaxRate::PURCHASES => 'default_purchase_tax_id',
    ];

    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected TaxRateRepository $taxRateRepository,
    ) {}

    public function settings(Workspace $workspace): array
    {
        $settings = [
            'tax_mode'        => $workspace->tax_mode,
            'price_tax_mode'  => $workspace->price_tax_mode,
            'tax_rounding'    => $workspace->tax_rounding,
            'withholding_tax' => (bool) $workspace->withholding_tax,
        ];

        foreach (self::DEFAULT_KEYS as $side => $key) {
            $settings[$key] = $this->taxRateRepository->defaultFor($workspace, $side)?->id;
        }

        return $settings;
    }

    /**
     * $data is validated: a default is an active rate of this business that
     * applies to its side. Options left out are left as they were - so the
     * choice alone can be saved, as business setup does.
     */
    public function save(Workspace $workspace, array $data): array
    {
        $before = $this->settings($workspace);

        DB::transaction(function () use ($workspace, $data) {
            $this->workspaceRepository->forceUpdate($workspace, Arr::only($data, ['tax_mode', 'price_tax_mode', 'tax_rounding', 'withholding_tax']));

            foreach (self::DEFAULT_KEYS as $side => $key) {
                if (array_key_exists($key, $data)) {
                    $this->taxRateRepository->setDefault($workspace, $side, $data[$key] ? $this->taxRateRepository->findFor($workspace, $data[$key]) : null);
                }
            }
        });

        $after = $this->settings($workspace);

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry. The choice saved on its own reads as what it is.
        $description = array_keys($data) === ['tax_mode'] ? "Set taxes to \"{$data['tax_mode']}\"" : 'Updated the tax settings';

        ActivityLogService::record('updated', $description, $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before))),
        ]);

        return $after;
    }
}
