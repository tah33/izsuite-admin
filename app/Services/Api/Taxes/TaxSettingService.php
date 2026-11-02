<?php

namespace App\Services\Api\Taxes;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\TaxRateRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Settings > Taxes (Doc 01 section 12, Doc 11 sections 9 and 11): whether the
 * business uses taxes - yes, no, or decide later - and its tax options: whether
 * prices include tax, how tax is rounded, its default sales, purchase and
 * expense tax, and its tax registration. The rates themselves are
 * TaxRateService's. Withholding tax is switched on apart from the rest
 * (section 9), and its rates are WithholdingTaxService's.
 *
 * Turning taxes off hides tax fields; it never deletes or changes what is
 * recorded in them. The choice lives on the workspace, and changing it bumps
 * nav_version (see Workspace), because the sidebar's payload carries it. The
 * registration - registered or not, the tax country and the numbers - is kept
 * with the business's own settings, so it needs them saved first.
 */
class TaxSettingService
{
    private const DEFAULT_KEYS = [
        TaxRate::SALES     => 'default_sales_tax_id',
        TaxRate::PURCHASES => 'default_purchase_tax_id',
        TaxRate::EXPENSES  => 'default_expense_tax_id',
    ];

    private const REGISTRATION_KEYS = ['tax_registration_status', 'tax_country', 'tax_1_name', 'tax_1_no', 'tax_2_name', 'tax_2_no'];

    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected TaxRateRepository $taxRateRepository,
        protected BusinessSettingRepository $businessSettingRepository,
    ) {}

    public function settings(Workspace $workspace): array
    {
        $business = $this->businessSettingRepository->findFor($workspace);

        $settings = [
            'tax_mode'        => $workspace->tax_mode,
            'price_tax_mode'  => $workspace->price_tax_mode,
            'tax_rounding'    => $workspace->tax_rounding,
            'withholding_tax' => (bool) $workspace->withholding_tax,
        ];

        foreach (self::DEFAULT_KEYS as $side => $key) {
            $settings[$key] = $this->taxRateRepository->defaultFor($workspace, $side)?->id;
        }

        foreach (self::REGISTRATION_KEYS as $key) {
            $settings[$key] = $business?->{$key};
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
        $before       = $this->settings($workspace);
        $registration = Arr::only($data, self::REGISTRATION_KEYS);
        $business     = $this->businessSettingRepository->findFor($workspace);

        if ($registration && ! $business) {
            throw ValidationException::withMessages(['tax_registration_status' => 'Save your business information first.']);
        }

        DB::transaction(function () use ($workspace, $data, $registration, $business) {
            $this->workspaceRepository->forceUpdate($workspace, Arr::only($data, ['tax_mode', 'price_tax_mode', 'tax_rounding', 'withholding_tax']));

            foreach (self::DEFAULT_KEYS as $side => $key) {
                if (array_key_exists($key, $data)) {
                    $this->taxRateRepository->setDefault($workspace, $side, $data[$key] ? $this->taxRateRepository->findFor($workspace, $data[$key]) : null);
                }
            }

            if ($registration) {
                $this->businessSettingRepository->update($business, $registration);
            }
        });

        $after = $this->settings($workspace);

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry. The choice saved on its own reads as what it is.
        $description = array_keys($data) === ['tax_mode'] ? "Set taxes to \"{$data['tax_mode']}\"" : 'Updated the tax settings';

        ActivityLogService::record('updated', $description, $workspace, [
            'source'  => 'api',
            'fields'  => array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before))),
            'changes' => SettingChanges::between($before, $after),
        ]);

        return $after;
    }
}
