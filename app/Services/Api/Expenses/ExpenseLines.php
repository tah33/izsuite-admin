<?php

namespace App\Services\Api\Expenses;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\TaxRateRepository;

/**
 * An expense's lines, from what the form sends to what is kept (see
 * ExpenseLine): the category with its name as it is now, the description,
 * quantity and unit amount as sent, and the tax as the rate is now, with
 * whether it is recoverable (Doc 10 sections 5 and 14).
 *
 * The lines come validated (see SaveExpenseRequest): their categories and
 * rates belong to the business.
 */
class ExpenseLines
{
    public function __construct(
        protected ExpenseCategoryRepository $expenseCategoryRepository,
        protected TaxRateRepository $taxRateRepository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function rows(Workspace $workspace, array $lines): array
    {
        $lines      = array_values($lines);
        $categories = $this->expenseCategoryRepository->findManyFor($workspace, array_filter(array_column($lines, 'expense_category_id')))->keyBy('id');
        $rates      = $this->taxRateRepository->findManyFor($workspace, array_filter(array_column($lines, 'tax_rate_id')))->keyBy('id');

        return array_map(function (array $line, int $index) use ($categories, $rates) {
            $category = $categories->get($line['expense_category_id'] ?? 0);

            return [
                'position'            => $index + 1,
                'expense_category_id' => $category?->id,
                'category_name'       => $category?->name,
                'description'         => $line['description'] ?? null,
                'quantity'            => (string) ($line['quantity'] ?? '0'),
                'unit_price'          => (string) ($line['unit_price'] ?? '0'),
                ...$this->tax($rates->get($line['tax_rate_id'] ?? 0)),
            ];
        }, $lines, array_keys($lines));
    }

    /** The tax as it is now - kept with the line, so changing the rate later never reaches it. */
    private function tax(?TaxRate $rate): array
    {
        return [
            'tax_rate_id'        => $rate?->id,
            'tax_name'           => $rate?->name,
            'tax_code'           => $rate?->code,
            'tax_rate'           => $rate ? (string) $rate->rate : null,
            'tax_treatment'      => $rate?->treatment->value,
            'tax_recoverability' => $rate?->recoverability,
        ];
    }
}
