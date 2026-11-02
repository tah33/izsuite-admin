<?php

namespace App\Services\Api\Expenses;

use App\Models\User\ExpenseSetting;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseSettingRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Settings > Expenses (Doc 11 section 17): the payment terms a payee without
 * their own has, the notes a new expense starts with, and whether recording
 * one checks for a receipt entered before, and the tax a new expense line
 * starts with when its category has none - the same default Settings > Taxes
 * keeps (TaxSettingService), marked on the rate. Expenses already made keep
 * their own. The categories are ExpenseCategoryService's.
 */
class ExpenseSettingService
{
    private const FIELDS = ['payment_terms', 'notes', 'duplicate_check', 'default_expense_tax_id'];

    public function __construct(
        protected ExpenseSettingRepository $expenseSettingRepository,
        protected TaxRateRepository $taxRateRepository,
    ) {}

    /** The settings, with the default expense tax's id (`default_expense_tax_id`, null for none) set on them. */
    public function settings(Workspace $workspace): ExpenseSetting
    {
        return $this->withDefaultTax($workspace, $this->expenseSettingRepository->forWorkspace($workspace));
    }

    /** $data is validated (see SaveExpenseSettingsRequest). */
    public function save(Workspace $workspace, array $data): ExpenseSetting
    {
        $before = $this->settings($workspace)->only(self::FIELDS);

        $settings = DB::transaction(function () use ($workspace, $data) {
            if (array_key_exists('default_expense_tax_id', $data)) {
                $rate = $data['default_expense_tax_id'] ? $this->taxRateRepository->findFor($workspace, $data['default_expense_tax_id']) : null;

                $this->taxRateRepository->setDefault($workspace, TaxRate::EXPENSES, $rate);
            }

            return $this->withDefaultTax($workspace, $this->expenseSettingRepository->save($workspace, Arr::except($data, 'default_expense_tax_id')));
        });

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry - with the fields that differ from before.
        ActivityLogService::record('updated', 'Updated the expense settings', $workspace, [
            'source' => 'api',
            'fields' => array_keys(array_diff_assoc(
                array_map('strval', $settings->only(self::FIELDS)),
                array_map('strval', $before),
            )),
        ]);

        return $settings;
    }

    private function withDefaultTax(Workspace $workspace, ExpenseSetting $settings): ExpenseSetting
    {
        $settings->default_expense_tax_id = $this->taxRateRepository->defaultFor($workspace, TaxRate::EXPENSES)?->id;

        return $settings;
    }
}
