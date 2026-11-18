<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ListSalesReturnsRequest;
use App\Http\Requests\Api\Sales\SaveReturnRequest;
use App\Http\Resources\Sales\SalesReturnListResource;
use App\Http\Resources\Sales\SalesReturnResource;
use App\Services\Api\Sales\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer returns (Doc 16 Phase E): the Returns page, one return, a draft
 * made from an order or one of its deliveries (POST /sales/orders/{id}/returns),
 * a change, and deleting a draft. Its steps are SalesReturnFlowController's.
 */
class SalesReturnController extends Controller
{
    public function __construct(
        protected SalesReturnService $salesReturnService,
    ) {}

    public function index(ListSalesReturnsRequest $request): JsonResponse
    {
        try {
            $returns = $this->salesReturnService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => SalesReturnListResource::collection($returns->items()),
                'pagination' => slim_pagination($returns),
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
                'data' => new SalesReturnResource($this->salesReturnService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A draft return of the order's delivered lines chosen. */
    public function store(SaveReturnRequest $request, int $id): JsonResponse
    {
        try {
            $return = $this->salesReturnService->create($this->currentWorkspace($request), $id, $request->validated(), $request->user());

            return response()->json([
                'message' => "Draft return made from sales order {$return->order->number}.",
                'data'    => new SalesReturnResource($return),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveReturnRequest $request, int $id): JsonResponse
    {
        try {
            $return = $this->salesReturnService->update($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Return saved.',
                'data'    => new SalesReturnResource($return),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->salesReturnService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Draft return deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}