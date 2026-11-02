<?php

namespace App\Services\Api\Setup;

use App\Jobs\CreateStarterWorkspace;
use App\Models\User\Workspace;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\RegionalDefaults;
use Illuminate\Validation\ValidationException;

/**
 * The First-Time Business Setup (Doc 11 section 3): a new business goes
 * through its eight steps (Workspace::SETUP_STEPS) before it opens the
 * Dashboard. Businesses made before the wizard count as set up.
 *
 * Each step saves through the setting it is about - the business profile,
 * the regional settings, the currencies, the taxes, the location, the sales
 * settings, a payment account - so it follows the same rules as Settings.
 * This keeps how far the business has got, so the wizard opens where it was
 * left: a step is reached only from the one before it, and only once the
 * business's own information is saved. Billing Starter, its Lite apps and the
 * default numbering come with the business itself.
 */
class SetupService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected BusinessSettingRepository $businessSettingRepository,
        protected CurrencyRepository $currencyRepository,
    ) {}

    /** The step reached, whether the setup is finished, and the currency the business's country pays in - when it is one a business may use. */
    public function show(Workspace $workspace): array
    {
        $currency = RegionalDefaults::currency($this->businessSettingRepository->findFor($workspace)?->country);

        return [
            'step'      => $workspace->setupStep(),
            'completed' => $workspace->isSetUp(),
            'currency'  => $currency && $this->currencyRepository->isActive($currency) ? $currency : null,
        ];
    }

    /**
     * Moves the setup on to $step, from the step before it. A step already
     * reached - going back to change one - leaves the setup where it got to.
     */
    public function reach(Workspace $workspace, string $step): array
    {
        $steps = array_keys(Workspace::SETUP_STEPS);
        $at    = array_search($workspace->setupStep(), $steps, true);
        $to    = array_search($step, $steps, true);

        if ($workspace->isSetUp() || $to <= $at) {
            ActivityLogService::skipForThisRequest();

            return $this->show($workspace);
        }

        if ($to > $at + 1) {
            throw ValidationException::withMessages(['step' => 'Finish the steps before this one first.']);
        }

        $business = $this->businessSettingRepository->findFor($workspace)
            ?? throw ValidationException::withMessages(['step' => 'Save your business information first.']);

        $was = $workspace->name;

        $this->workspaceRepository->forceUpdate($workspace, [
            'setup_step' => $step,
            // Registration names a business after its plan until it has a name of its own (see CreateStarterWorkspace).
            ...($was === CreateStarterWorkspace::NAME ? ['name' => $business->trading_name ?: $business->business_name] : []),
        ]);

        ActivityLogService::record('updated', 'Reached "'.Workspace::SETUP_STEPS[$step].'" in the business setup', $workspace, [
            'source' => 'api',
            ...($workspace->name !== $was ? ['renamed_from' => $was] : []),
        ]);

        return $this->show($workspace);
    }

    /** Finishes the setup once every step before Review and Complete is behind it: from now on the business opens its Dashboard. */
    public function complete(Workspace $workspace): array
    {
        if ($workspace->isSetUp()) {
            ActivityLogService::skipForThisRequest();

            return $this->show($workspace);
        }

        if ($workspace->setupStep() !== array_key_last(Workspace::SETUP_STEPS)) {
            throw ValidationException::withMessages(['step' => 'Finish the steps before this one first.']);
        }

        $this->workspaceRepository->forceUpdate($workspace, ['setup_completed_at' => now()]);

        ActivityLogService::record('updated', 'Finished the business setup', $workspace, ['source' => 'api']);

        return $this->show($workspace);
    }
}
