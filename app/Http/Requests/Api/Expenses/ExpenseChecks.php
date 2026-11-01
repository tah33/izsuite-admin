<?php

namespace App\Http\Requests\Api\Expenses;

use App\Http\Requests\Api\Concerns\ChecksLocation;
use App\Models\User\Expense;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\ExpenseRepository;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Validation\Validator;

/**
 * What SaveExpenseRequest checks with the database, once its own rules have
 * passed:
 *
 * - a payee contact is a supplier (Doc 10 section 5) and active;
 * - each category is this business's and active (section 7);
 * - each tax is one of the business's expense taxes, active and in effect on
 *   the expense's date.
 *
 * What a draft being edited already has may stay even if it has been switched
 * off since - its payee, categories and taxes - so the rest of it can still
 * be changed.
 */
class ExpenseChecks
{
    use ChecksLocation;

    public function __construct(
        protected ContactRepository $contactRepository,
        protected ExpenseCategoryRepository $expenseCategoryRepository,
        protected TaxRateRepository $taxRateRepository,
        protected ExpenseRepository $expenseRepository,
    ) {}

    public function check(Validator $validator, Workspace $workspace, ?int $id, array $input): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $existing = $id ? $this->expenseRepository->findFor($workspace, $id) : null;

        $this->checkPayee($validator, $workspace, $input, $existing);
        $this->checkLocation($validator, $workspace, $input['location_id'] ?? null, $existing?->location_id);
        $this->checkCategories($validator, $workspace, $input['lines'], $existing);
        $this->checkTaxes($validator, $workspace, $input['lines'], $input['document_date'], $existing);
    }

    private function checkPayee(Validator $validator, Workspace $workspace, array $input, ?Expense $existing): void
    {
        $contact = empty($input['contact_id']) ? null : $this->contactRepository->findFor($workspace, (int) $input['contact_id']);

        if ($contact && ! $contact->isSupplier()) {
            $validator->errors()->add('contact_id', "{$contact->name} is only a customer. Choose a supplier, or type the payee's name.");
        } elseif ($contact && ! $contact->is_active && $contact->id !== $existing?->contact_id) {
            $validator->errors()->add('contact_id', "{$contact->name} is inactive.");
        }
    }

    private function checkCategories(Validator $validator, Workspace $workspace, array $lines, ?Expense $existing): void
    {
        $categories = $this->expenseCategoryRepository->findManyFor($workspace, array_filter(array_column($lines, 'expense_category_id')))->keyBy('id');
        $kept       = $existing ? $existing->lines->pluck('expense_category_id')->filter()->all() : [];

        foreach ($lines as $index => $line) {
            $category = $categories->get((int) $line['expense_category_id']);
            $message  = match (true) {
                $category === null                                              => 'Choose one of your expense categories.',
                ! $category->is_active && ! in_array($category->id, $kept, true) => "{$category->name} is switched off.",
                default                                                         => null,
            };

            if ($message) {
                $validator->errors()->add("lines.{$index}.expense_category_id", $message);
            }
        }
    }

    private function checkTaxes(Validator $validator, Workspace $workspace, array $lines, string $date, ?Expense $existing): void
    {
        $rates = $this->taxRateRepository->findManyFor($workspace, array_filter(array_column($lines, 'tax_rate_id')))->keyBy('id');
        $kept  = $existing ? $existing->lines->pluck('tax_rate_id')->filter()->all() : [];

        foreach ($lines as $index => $line) {
            if (empty($line['tax_rate_id'])) {
                continue;
            }

            $rate    = $rates->get((int) $line['tax_rate_id']);
            $isKept  = $rate && in_array($rate->id, $kept, true);
            $message = match (true) {
                $rate === null || ! $rate->for_expenses                 => 'Choose one of your expense taxes.',
                $isKept                                                 => null,
                ! $rate->is_active                                      => "{$rate->name} is inactive.",
                $rate->effective_from > $date || ($rate->effective_to && $rate->effective_to < $date) => "{$rate->name} is not in effect on this date.",
                default                                                 => null,
            };

            if ($message) {
                $validator->errors()->add("lines.{$index}.tax_rate_id", $message);
            }
        }
    }
}
