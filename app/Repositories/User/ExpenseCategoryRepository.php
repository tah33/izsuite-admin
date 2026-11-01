<?php

namespace App\Repositories\User;

use App\Models\User\ExpenseCategory;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * The expense_categories table: each business's one list (Doc 10 section 7).
 * Every query is the business's own.
 */
class ExpenseCategoryRepository
{
    /** The whole list by name, each saying whether an expense line holds it (`in_use`). */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->expenseCategories()->withExists('lines as in_use')->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?ExpenseCategory
    {
        return $workspace->expenseCategories()->withExists('lines as in_use')->find($id);
    }

    /** The business's categories among $ids. */
    public function findManyFor(Workspace $workspace, array $ids): Collection
    {
        return $ids ? $workspace->expenseCategories()->whereIn('id', $ids)->get() : new Collection;
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): ExpenseCategory
    {
        return $workspace->expenseCategories()->create($data);
    }

    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category->update($data);

        return $category;
    }

    public function delete(ExpenseCategory $category): void
    {
        $category->delete();
    }

    /**
     * The starter list (ExpenseCategory::STARTER), each made unless the
     * business has one of that name already. Returns how many were made.
     */
    public function addStarter(Workspace $workspace): int
    {
        return collect(ExpenseCategory::STARTER)
            ->filter(fn (string $name) => $workspace->expenseCategories()->firstOrCreate(['name' => $name])->wasRecentlyCreated)
            ->count();
    }

    /** How many categories start their lines with $rate. */
    public function taxRateCount(TaxRate $rate): int
    {
        return ExpenseCategory::where('tax_rate_id', $rate->id)->count();
    }
}
