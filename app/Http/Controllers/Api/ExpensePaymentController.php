<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\ListExpensePaymentsRequest;
use App\Http\Requests\Api\Expenses\RecordExpensePaymentRequest;
use App\Http\Requests\Api\Expenses\ReverseExpensePaymentRequest;
use App\Http\Resources\Expenses\ExpensePaymentListResource;
use App\Http\Resources\Expenses\ExpensePaymentResource;
use App\Services\Api\Expenses\ExpensePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expense payments (Doc 10 sections 3 and 12): Payments Made, a payment
 * against a recorded expense (POST /expenses/{id}/payments) - its voucher -
 * and reversing a payment that was a mistake.
 */
class ExpensePaymentController extends Controller
{
    public function __construct(
        protected ExpensePaymentService $expensePaymentService,
    ) {}

    /** A page of payments made, newest first, searched and filtered. */
    public function index(ListExpensePaymentsRequest $request): JsonResponse
    {
        try {
            $payments = $this->expensePaymentService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => ExpensePaymentListResource::collection($payments->items()),
                'pagination' => slim_pagination($payments),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ExpensePaymentResource($this->expensePaymentService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** POST /expenses/{id}/payments: a payment against the expense, with its voucher number. */
    public function store(RecordExpensePaymentRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->expensePaymentService->record($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => "Payment recorded. Voucher {$payment->number}.",
                'data'    => new ExpensePaymentResource($payment),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reverse(ReverseExpensePaymentRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->expensePaymentService->reverse($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return response()->json([
                'message' => "Payment {$payment->number} reversed.",
                'data'    => new ExpensePaymentResource($payment),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
