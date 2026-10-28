<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ListSalesPaymentsRequest;
use App\Http\Requests\Api\Sales\RecordPaymentRequest;
use App\Http\Requests\Api\Sales\SalesStatusRequest;
use App\Http\Resources\Sales\SalesPaymentListResource;
use App\Http\Resources\Sales\SalesPaymentResource;
use App\Services\Api\Sales\SalesPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer payments (Doc 08 sections 2 and 13): Payments Received - a list to
 * look at, since a payment is recorded from its invoice
 * (POST /sales/invoices/{id}/payments) - a receipt, and reversing a payment or
 * refund that was a mistake.
 */
class SalesPaymentController extends Controller
{
    public function __construct(
        protected SalesPaymentService $salesPaymentService,
    ) {}

    /** A page of payments received, newest first, searched and filtered. */
    public function index(ListSalesPaymentsRequest $request): JsonResponse
    {
        try {
            $payments = $this->salesPaymentService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => SalesPaymentListResource::collection($payments->items()),
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
                'data' => new SalesPaymentResource($this->salesPaymentService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** POST /sales/invoices/{id}/payments: a payment against the invoice, with its receipt number. */
    public function store(RecordPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->salesPaymentService->record($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => "Payment recorded. Receipt {$payment->number}.",
                'data'    => new SalesPaymentResource($payment),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reverse(SalesStatusRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->salesPaymentService->reverse($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return response()->json([
                'message' => $payment->isRefund() ? 'Refund reversed.' : "Payment {$payment->number} reversed.",
                'data'    => new SalesPaymentResource($payment),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
