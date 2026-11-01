<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\ListExpensesRequest;
use App\Http\Requests\Api\Expenses\SaveExpenseRequest;
use App\Http\Resources\Expenses\ExpenseListResource;
use App\Http\Resources\Expenses\ExpenseResource;
use App\Services\Api\Expenses\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses Lite's expenses (Doc 10): the list, one expense whole, a draft
 * added, changed or deleted, and the list as CSV.
 */
class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService,
    ) {}

    public function index(ListExpensesRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $expenses  = $this->expenseService->list($workspace, $request->validated(), requested_per_page());
            $today     = $this->expenseService->today($workspace);

            return response()->json([
                'data'       => array_map(fn ($expense) => new ExpenseListResource($expense, $today), $expenses->items()),
                'pagination' => slim_pagination($expenses),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(ListExpensesRequest $request): StreamedResponse
    {
        try {
            return $this->expenseService->export($this->currentWorkspace($request), $request->validated());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveExpenseRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $expense   = $this->expenseService->create($workspace, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Draft expense saved.',
                'data'    => new ExpenseResource($expense, $this->expenseService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** One expense, whole - for its page and its edit form. */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);

            return response()->json([
                'data' => new ExpenseResource($this->expenseService->find($workspace, $id), $this->expenseService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $expense   = $this->expenseService->update($workspace, $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Expense saved.',
                'data'    => new ExpenseResource($expense, $this->expenseService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Only a draft is deleted; a recorded expense is voided instead. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->expenseService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Draft expense deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
