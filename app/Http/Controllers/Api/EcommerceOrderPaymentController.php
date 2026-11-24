<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\FailEcommercePaymentRequest;
use App\Http\Requests\Api\Ecommerce\RecordEcommercePaymentRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderPaymentReviewResource;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Services\Api\Ecommerce\EcommerceOrderPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An online order's payment (Doc 19 Phase F3), under Confirm Orders: what
 * recording one would do, recording it, and marking it failed.
 */
class EcommerceOrderPaymentController extends Controller
{
    public function __construct(
        protected EcommerceOrderPayments $payments,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $review = $this->payments->review($this->currentWorkspace($request), $id);

            return response()->json(['data' => new EcommerceOrderPaymentReviewResource($review)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(RecordEcommercePaymentRequest $request, int $id): JsonResponse
    {
        try {
            ['order' => $order, 'payment' => $payment] = $this->payments->record($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json(['message' => "Payment {$payment->number} recorded for order {$order->number}.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function fail(FailEcommercePaymentRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->payments->fail($this->currentWorkspace($request), $request->user(), $id, trim($request->validated('reason')));

            return response()->json(['message' => "The payment of order {$order->number} is marked failed, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
