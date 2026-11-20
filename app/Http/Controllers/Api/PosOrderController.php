<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CompletePosOrderRequest;
use App\Http\Requests\Api\Pos\ListPosOrdersRequest;
use App\Http\Resources\Pos\PosOrderListResource;
use App\Http\Resources\Pos\PosOrderResource;
use App\Services\Api\Pos\PosOrderService;
use App\Services\Api\Pos\PosRecordLists;
use Illuminate\Http\JsonResponse;

/** POS orders (Doc 18 Phase C2): a sale paid and completed at the cashier's register. */
class PosOrderController extends Controller
{
    public function __construct(
        protected PosOrderService $orderService,
        protected PosRecordLists $lists,
    ) {}

    /** Phase G1: the page of orders. */
    public function index(ListPosOrdersRequest $request): JsonResponse
    {
        try {
            $page = $this->lists->orders($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json(['data' => PosOrderListResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(CompletePosOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->complete($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => "Sale {$order->number} completed.",
                'data'    => new PosOrderResource($order),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
