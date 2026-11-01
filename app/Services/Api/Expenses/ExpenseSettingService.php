<?php

namespace App\Services\Api\Expenses;

use App\Models\User\ExpenseSetting;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseSettingRepository;
use App\Services\Shared\ActivityLogService;

/**
 * Settings > Expenses (Doc 11 section 17): the payment terms a payee without
 * their own has, the notes a new expense starts with, and whether recording
 * one checks for a receipt entered before. Expenses already made keep their
 * own. The categories are ExpenseCategoryService's; the default expense tax
 * sits with the other default taxes (TaxSettingService).
 */
class ExpenseSettingService
{
    private const FIELDS = ['payment_terms', 'notes', 'duplicate_check'];

    public function __construct(
        protected ExpenseSettingRepository $expenseSettingRepository,
    ) {}

    public function settings(Workspace $workspace): ExpenseSetting
    {
        return $this->expenseSettingRepository->forWorkspace($workspace);
    }

    /** $data is validated (see SaveExpenseSettingsRequest). */
    public function save(Workspace $workspace, array $data): ExpenseSetting
    {
        $before   = $this->settings($workspace)->only(self::FIELDS);
        $settings = $this->expenseSettingRepository->save($workspace, $data);

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
}
