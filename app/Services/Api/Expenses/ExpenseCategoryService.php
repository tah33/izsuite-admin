<?php

namespace App\Services\Api\Expenses;

use App\Models\User\ExpenseCategory;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\ExpenseRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Settings > Expenses > Expense Categories (Doc 10 section 7, Doc 11 section
 * 17): the business's one list - added there or from the expense form, each
 * with the tax its lines start with. Renaming one leaves recorded expenses
 * with the name they kept. One an expense line holds is switched off rather
 * than deleted; switched off, it is offered to no new expense.
 */
class ExpenseCategoryService
{
    use RefusesConflicts;

    public function __construct(
        protected ExpenseCategoryRepository $expenseCategoryRepository,
        protected ExpenseRepository $expenseRepository,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->expenseCategoryRepository->getFor($workspace);
    }

    /** $data is validated (see SaveExpenseCategoryRequest). */
    public function create(Workspace $workspace, array $data): ExpenseCategory
    {
        $category = $this->expenseCategoryRepository->create($workspace, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer entry for the same request.
        ActivityLogService::record('created', "Added expense category {$this->quoted($category)}", $category, ['source' => 'api']);

        return $this->find($workspace, $category->id);
    }

    public function update(Workspace $workspace, int $id, array $data): ExpenseCategory
    {
        $category = $this->find($workspace, $id);

        $this->expenseCategoryRepository->update($category, $data);

        ActivityLogService::record('updated', "Updated expense category {$this->quoted($category)}", $category, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($category->getChanges(), ['updated_at' => true])),
        ]);

        return $category;
    }

    /** Only an unused category can be deleted (section 7): one on an expense is switched off instead. */
    public function delete(Workspace $workspace, int $id): void
    {
        $category = $this->find($workspace, $id);

        if ($this->expenseRepository->usesCategory($category)) {
            $this->refuse('This category is on expenses, so it cannot be deleted. Switch it off instead.');
        }

        $this->expenseCategoryRepository->delete($category);

        ActivityLogService::record('deleted', "Deleted expense category {$this->quoted($category)}", $category, ['source' => 'api']);
    }

    /** Another business's category answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): ExpenseCategory
    {
        return $this->expenseCategoryRepository->findFor($workspace, $id) ?? abort(404, 'Expense category not found.');
    }

    private function quoted(ExpenseCategory $category): string
    {
        return '"'.Str::limit($category->name, 100).'"';
    }
}
