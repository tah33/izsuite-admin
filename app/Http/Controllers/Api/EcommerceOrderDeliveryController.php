<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\DeliverEcommerceProductRequest;
use App\Http\Requests\Api\Ecommerce\EcommerceOrderReasonRequest;
use App\Http\Requests\Api\Ecommerce\PerformEcommerceServiceRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Models\User\EcommerceOrder;
use App\Services\Api\Ecommerce\EcommerceOrderDeliveries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An online order's digital products delivered and its services performed
 * (Doc 19 Phase G3a), under Manage Fulfilment: a digital product's license
 * keys given, or its delivery by hand - or its access given again - a
 * service performed, and a delivered product's access revoked; from Phase
 * G3b, a delivered download given more. Each answers with the order as its
 * page shows it.
 */
class EcommerceOrderDeliveryController extends Controller
{
    public function __construct(
        protected EcommerceOrderDeliveries $deliveries,
    ) {}

    public function deliver(DeliverEcommerceProductRequest $request, int $id, int $line): JsonResponse
    {
        try {
            $order = $this->deliveries->deliver($this->currentWorkspace($request), $request->user(), $id, $line, $request->validated());

            return response()->json(['message' => "{$this->titleOf($order, $line)} of order {$order->number} is delivered, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function perform(PerformEcommerceServiceRequest $request, int $id, int $line): JsonResponse
    {
        try {
            $order = $this->deliveries->perform($this->currentWorkspace($request), $request->user(), $id, $line, $request->validated());

            return response()->json(['message' => "{$this->titleOf($order, $line)} of order {$order->number} is performed, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function revoke(EcommerceOrderReasonRequest $request, int $id, int $line): JsonResponse
    {
        try {
            $order = $this->deliveries->revoke($this->currentWorkspace($request), $request->user(), $id, $line, trim($request->validated('reason')));

            return response()->json(['message' => "Access to {$this->titleOf($order, $line)} of order {$order->number} is revoked.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase G3b: a delivered download given more - its days and count afresh. */
    public function renew(Request $request, int $id, int $line): JsonResponse
    {
        try {
            $order = $this->deliveries->renew($this->currentWorkspace($request), $id, $line);

            return response()->json(['message' => "{$this->titleOf($order, $line)} of order {$order->number} may be downloaded again, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** What the order's line $id is called in a message. */
    private function titleOf(EcommerceOrder $order, int $id): string
    {
        return $order->lines->firstWhere('id', $id)?->title ?? 'The product';
    }
}
