<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchases\ListSupplierPaymentsRequest;
use App\Http\Requests\Api\Purchases\RecordSupplierPaymentRequest;
use App\Http\Requests\Api\Purchases\ReverseSupplierPaymentRequest;
use App\Http\Resources\Purchases\SupplierPaymentListResource;
use App\Http\Resources\Purchases\SupplierPaymentResource;
use App\Services\Api\Purchases\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supplier payments (Doc 09 sections 4 and 12): Payments Made, a payment
 * against a recorded purchase (POST /purchases/{id}/payments) - its voucher -
 * and reversing a payment that was a mistake.
 */
class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $supplierPaymentService,
    ) {}

    /** A page of payments made, newest first, searched and filtered. */
    public function index(ListSupplierPaymentsRequest $request): JsonResponse
    {
        try {
            $payments = $this->supplierPaymentService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => SupplierPaymentListResource::collection($payments->items()),
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
                'data' => new SupplierPaymentResource($this->supplierPaymentService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** POST /purchases/{id}/payments: a payment against the purchase, with its voucher number. */
    public function store(RecordSupplierPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->supplierPaymentService->record($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => "Payment recorded. Voucher {$payment->number}.",
                'data'    => new SupplierPaymentResource($payment),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reverse(ReverseSupplierPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->supplierPaymentService->reverse($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return response()->json([
                'message' => "Payment {$payment->number} reversed.",
                'data'    => new SupplierPaymentResource($payment),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
