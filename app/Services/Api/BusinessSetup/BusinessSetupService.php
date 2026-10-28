<?php

namespace App\Services\Api\BusinessSetup;

use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business's currencies (Doc 01 section 11): Settings > Currencies. Its tax
 * choice is TaxSettingService's, and its exchange rates ExchangeRateService's.
 *
 * They live on the workspace. A change bumps its nav_version (see Workspace),
 * because the sidebar's payload carries them to every page.
 */
class BusinessSetupService
{
    public function __construct(
        protected WorkspaceRepository $workspaceRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected ContactRepository $contactRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected AccountTransactionRepository $accountTransactionRepository,
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
     * be left in a currency the business no longer takes. The base currency
     * itself changes freely only until the business has issued or sent a
     * document or given a payment account an opening balance (Doc 11 section
     * 7): after that its totals are in it.
     */
    public function saveCurrencies(Workspace $workspace, array $data): array
    {
        $additional = array_values(array_unique($data['additional_currencies'] ?? []));
        sort($additional);

        if ($data['base_currency'] !== $workspace->base_currency
            && ($this->salesDocumentRepository->hasTransactions($workspace) || $this->paymentAccountRepository->hasOpeningBalance($workspace)
                || $this->accountTransactionRepository->existsForWorkspace($workspace))) {
            throw ValidationException::withMessages([
                'base_currency' => 'The base currency cannot change once documents have been issued or sent, or a payment account has an opening balance or money recorded through it.',
            ]);
        }

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
}
