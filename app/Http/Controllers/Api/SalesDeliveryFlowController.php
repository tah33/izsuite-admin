<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\DeliverRequest;
use App\Http\Requests\Api\Sales\IssueSalesDocumentRequest;
use App\Http\Requests\Api\Sales\SalesStatusRequest;
use App\Http\Resources\Sales\SalesDeliveryResource;
use App\Models\User\SalesDelivery;
use App\Services\Api\Sales\SalesDeliveryService;
use Illuminate\Http\JsonResponse;

/**
 * A delivery's status changes (see SalesDeliveryService): Ready, Delivered,
 * Cancel. Each answers with the delivery as it is now.
 */
class SalesDeliveryFlowController extends Controller
{
    public function __construct(
        protected SalesDeliveryService $salesDeliveryService,
    ) {}

    public function ready(IssueSalesDocumentRequest $request, int $id): JsonResponse
    {
        try {
            $delivery = $this->salesDeliveryService->ready($this->currentWorkspace($request), $id, $request->user(), $request->validated('number'));

            return $this->answer($delivery, "Delivery {$delivery->number} is ready.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deliver(DeliverRequest $request, int $id): JsonResponse
    {
        try {
            $delivery = $this->salesDeliveryService->deliver($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return $this->answer($delivery, "Delivery {$delivery->number} delivered.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancel(SalesStatusRequest $request, int $id): JsonResponse
    {
        try {
            $delivery = $this->salesDeliveryService->cancel($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return $this->answer($delivery, "Delivery {$delivery->number} cancelled.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function answer(SalesDelivery $delivery, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => new SalesDeliveryResource($delivery)]);
    }
}
