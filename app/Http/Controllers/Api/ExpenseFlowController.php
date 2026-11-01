<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\RecordExpenseRequest;
use App\Http\Requests\Api\Expenses\VoidExpenseRequest;
use App\Http\Resources\Expenses\ExpenseResource;
use App\Models\User\Expense;
use App\Services\Api\Expenses\ExpenseFlow;
use App\Services\Api\Expenses\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** An expense's status changes (Doc 10 section 10): recording a draft, and voiding a recorded one. */
class ExpenseFlowController extends Controller
{
    public function __construct(
        protected ExpenseFlow $expenseFlow,
        protected ExpenseService $expenseService,
    ) {}

    public function record(RecordExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->expenseFlow->record($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return $this->answer($request, $expense, "Expense {$expense->number} recorded.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function void(VoidExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->expenseFlow->void($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return $this->answer($request, $expense, "Expense {$expense->number} voided.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function answer(Request $request, Expense $expense, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => new ExpenseResource($expense, $this->expenseService->today($this->currentWorkspace($request))),
        ]);
    }
}
