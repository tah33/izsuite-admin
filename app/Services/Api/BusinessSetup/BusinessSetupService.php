<?php

namespace App\Services\Api\BusinessSetup;

use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business's currencies and tax choice (Doc 01 sections 11 and 12): Settings
 * > Currencies and Settings > Taxes.
 *
 * Both live on the workspace. A change to either bumps its nav_version (see
 * Workspace), because the sidebar's payload carries them to every page.
 */
class BusinessSetupService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected ContactRepository $contactRepository,
    ) {}

    public function currencies(Workspace $workspace): array
    {
        return [
            'base_currency'         => $workspace->base_currency,
            'additional_currencies' => array_values($workspace->additional_currencies ?? []),
        ];
    }

    /**
     * $data is validated: the base currency and the additional ones are all
     * active platform currencies, and none of the additional ones is the base.
     *
     * A currency a payment account uses cannot be dropped - the account would
     * be left in a currency the business no longer takes. Once transactions
     * exist, Doc 01 also locks the base currency itself; nothing records
     * transactions yet, so that check arrives with the first app that does.
     */
    public function saveCurrencies(Workspace $workspace, array $data): array
    {
        $additional = array_values(array_unique($data['additional_currencies'] ?? []));
        sort($additional);

        $kept   = [$data['base_currency'], ...$additional];
        $orphan = $this->paymentAccountRepository->firstOutside($workspace, $kept);

        if ($orphan) {
            throw ValidationException::withMessages([
                'additional_currencies' => "{$orphan->currency} is used by the payment account \"{$orphan->name}\". Keep it, or change that account first.",
            ]);
        }

        DB::transaction(function () use ($workspace, $data, $additional, $kept) {
            $this->workspaceRepository->forceUpdate($workspace, [
                'base_currency'         => $data['base_currency'],
                'additional_currencies' => $additional ?: null,
            ]);

            // A contact's default currency that the business stops using gives
            // way to the base currency, which is what no default means (Doc 03
            // section 7). Only new transactions follow defaults, so nothing
            // already issued changes.
            foreach (['sales_currency', 'purchase_currency'] as $column) {
                $this->contactRepository->clearCurrencyOutside($workspace, $column, $kept);
            }
        });

        // Logged here, like every save, so LogApiWriteActivity does not write its
        // own vaguer entry. nav_version goes up with the change - bookkeeping,
        // not something the user changed.
        ActivityLogService::record('updated', 'Updated the business currencies', $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($workspace->getChanges(), ['updated_at' => true, 'nav_version' => true])),
        ]);

        return $this->currencies($workspace);
    }

    public function taxes(Workspace $workspace): array
    {
        return ['tax_mode' => $workspace->tax_mode];
    }

    /**
     * Turning taxes off hides tax fields; it never deletes or changes tax
     * details already recorded (Doc 01 section 12).
     */
    public function saveTaxes(Workspace $workspace, string $mode): array
    {
        $this->workspaceRepository->forceUpdate($workspace, ['tax_mode' => $mode]);

        ActivityLogService::record('updated', "Set taxes to \"{$mode}\"", $workspace, [
            'source' => 'api',
            'fields' => $workspace->wasChanged('tax_mode') ? ['tax_mode'] : [],
        ]);

        return $this->taxes($workspace);
    }
}
