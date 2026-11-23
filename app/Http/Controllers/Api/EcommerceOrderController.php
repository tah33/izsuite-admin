<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListEcommerceOrdersRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderListResource;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Services\Api\Ecommerce\EcommerceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The business's online orders (Doc 19 Phase F1), under View Orders: the Orders page and one order. */
class EcommerceOrderController extends Controller
{
    public function __construct(
        protected EcommerceOrderService $orderService,
    ) {}

    public function index(ListEcommerceOrdersRequest $request): JsonResponse
    {
        try {
            $page = $this->orderService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json(['data' => EcommerceOrderListResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->orderService->find($this->currentWorkspace($request), $id);

            return response()->json(['data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
