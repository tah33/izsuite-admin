<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\EcommerceOrderReasonRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Services\Api\Ecommerce\EcommerceOrderCancellation;
use App\Services\Api\Ecommerce\EcommerceOrderHolds;
use Illuminate\Http\JsonResponse;

/**
 * An online order put on hold and released (under Place Orders On Hold), and
 * cancelled (under Cancel Orders) - each with a reason (Doc 19 Phase F4).
 */
class EcommerceOrderStatusController extends Controller
{
    public function __construct(
        protected EcommerceOrderHolds $holds,
        protected EcommerceOrderCancellation $cancellation,
    ) {}

    public function hold(EcommerceOrderReasonRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->holds->hold($this->currentWorkspace($request), $id, trim($request->validated('reason')));

            return response()->json(['message' => "Order {$order->number} is on hold.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function release(EcommerceOrderReasonRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->holds->release($this->currentWorkspace($request), $id, trim($request->validated('reason')));

            return response()->json(['message' => "Order {$order->number} is released.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancel(EcommerceOrderReasonRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->cancellation->cancel($this->currentWorkspace($request), $request->user(), $id, trim($request->validated('reason')));

            return response()->json(['message' => "Order {$order->number} is cancelled, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
