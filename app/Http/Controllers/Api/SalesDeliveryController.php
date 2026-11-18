<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\CreateDeliveryRequest;
use App\Http\Requests\Api\Sales\ListSalesDeliveriesRequest;
use App\Http\Requests\Api\Sales\SaveDeliveryRequest;
use App\Http\Resources\Sales\SalesDeliveryListResource;
use App\Http\Resources\Sales\SalesDeliveryResource;
use App\Services\Api\Sales\SalesDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sales orders' delivery notes (Doc 16 Phase C): the Deliveries page, one
 * delivery, a draft made from an order (POST /sales/orders/{id}/deliveries),
 * a change, deleting a draft, and its PDF. Ready, Delivered and Cancel are
 * SalesDeliveryFlowController's.
 */
class SalesDeliveryController extends Controller
{
    public function __construct(
        protected SalesDeliveryService $salesDeliveryService,
    ) {}

    public function index(ListSalesDeliveriesRequest $request): JsonResponse
    {
        try {
            $deliveries = $this->salesDeliveryService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => SalesDeliveryListResource::collection($deliveries->items()),
                'pagination' => slim_pagination($deliveries),
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
                'data' => new SalesDeliveryResource($this->salesDeliveryService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A draft delivery of the order's lines chosen. */
    public function store(CreateDeliveryRequest $request, int $id): JsonResponse
    {
        try {
            $delivery = $this->salesDeliveryService->create($this->currentWorkspace($request), $id, $request->quantities(), $request->user());

            return response()->json([
                'message' => "Draft delivery made from sales order {$delivery->order->number}.",
                'data'    => new SalesDeliveryResource($delivery),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveDeliveryRequest $request, int $id): JsonResponse
    {
        try {
            $delivery = $this->salesDeliveryService->update($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Delivery saved.',
                'data'    => new SalesDeliveryResource($delivery),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->salesDeliveryService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Draft delivery deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Its delivery note, to preview, print or, with ?download=1, save. */
    public function pdf(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->salesDeliveryService->pdf($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
