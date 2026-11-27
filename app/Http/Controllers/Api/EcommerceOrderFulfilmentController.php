<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\CompleteEcommerceFulfilmentRequest;
use App\Http\Requests\Api\Ecommerce\EcommerceOrderReasonRequest;
use App\Http\Requests\Api\Ecommerce\ReadyEcommerceOrderRequest;
use App\Http\Requests\Api\Ecommerce\ShipEcommerceOrderRequest;
use App\Http\Requests\Api\Ecommerce\UpdateEcommerceFulfilmentRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderFulfilmentReviewResource;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Models\User\EcommerceOrder;
use App\Services\Api\Ecommerce\EcommerceOrderFulfilments;
use App\Services\Api\Ecommerce\EcommercePackingSlips;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * An online order's shipments and pickup (Doc 19 Phase G1), under Manage
 * Fulfilment: what fulfilling it would do, a shipment, readiness for pickup,
 * a shipment's details corrected, one delivered or collected, and one
 * cancelled. Each answers with the order as its page shows it. And (Phase
 * G2) a shipment's packing slip, under View Orders.
 */
class EcommerceOrderFulfilmentController extends Controller
{
    public function __construct(
        protected EcommerceOrderFulfilments $fulfilments,
        protected EcommercePackingSlips $packingSlips,
    ) {}

    public function review(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => new EcommerceOrderFulfilmentReviewResource($this->fulfilments->review($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function ship(ShipEcommerceOrderRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->fulfilments->ship($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json(['message' => ucfirst($order->fulfilments->last()->label())." of order {$order->number} is on its way, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function ready(ReadyEcommerceOrderRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->fulfilments->ready($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json(['message' => "Order {$order->number} is ready for pickup, and the customer told.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateEcommerceFulfilmentRequest $request, int $id, int $fulfilment): JsonResponse
    {
        try {
            $order = $this->fulfilments->update($this->currentWorkspace($request), $id, $fulfilment, $request->validated());

            return response()->json(['message' => 'The details of '.$this->labelOf($order, $fulfilment)." of order {$order->number} are saved.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function complete(CompleteEcommerceFulfilmentRequest $request, int $id, int $fulfilment): JsonResponse
    {
        try {
            $order = $this->fulfilments->complete($this->currentWorkspace($request), $id, $fulfilment, $request->validated('completed_on'));

            return response()->json([
                'message' => $order->delivery === EcommerceOrder::PICKUP
                    ? "Order {$order->number} is collected, and the customer told."
                    : ucfirst($this->labelOf($order, $fulfilment))." of order {$order->number} is delivered, and the customer told.",
                'data'    => new EcommerceOrderResource($order),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancel(EcommerceOrderReasonRequest $request, int $id, int $fulfilment): JsonResponse
    {
        try {
            $order = $this->fulfilments->cancel($this->currentWorkspace($request), $id, $fulfilment, trim($request->validated('reason')));

            return response()->json(['message' => ucfirst($this->labelOf($order, $fulfilment))." of order {$order->number} is cancelled.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase G2: a shipment's packing slip, to preview, print or, with ?download=1, save. */
    public function packingSlip(Request $request, int $id, int $fulfilment): Response
    {
        try {
            [$filename, $pdf] = $this->packingSlips->pdf($this->currentWorkspace($request), $id, $fulfilment);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** What the order's fulfilment $id is called in a message: "shipment 2", or "the pickup". */
    private function labelOf(EcommerceOrder $order, int $id): string
    {
        $label = $order->fulfilments->firstWhere('id', $id)?->label() ?? 'shipment';

        return $label === 'pickup' ? 'the pickup' : $label;
    }
}
