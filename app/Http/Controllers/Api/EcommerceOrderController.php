<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListEcommerceOrdersRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderConfirmationResource;
use App\Http\Resources\Ecommerce\EcommerceOrderListResource;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Services\Api\Ecommerce\EcommerceOrderConfirmation;
use App\Services\Api\Ecommerce\EcommerceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's online orders (Doc 19 Phase F1), under View Orders: the
 * Orders page and one order - and (Phase F2), under Confirm Orders, what
 * confirming an order would do, and confirming it.
 */
class EcommerceOrderController extends Controller
{
    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderConfirmation $confirmation,
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

    public function confirmation(Request $request, int $id): JsonResponse
    {
        try {
            $review = $this->confirmation->review($this->currentWorkspace($request), $id);

            return response()->json(['data' => new EcommerceOrderConfirmationResource($review)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->confirmation->confirm($this->currentWorkspace($request), $request->user(), $id);

            return response()->json(['message' => "Order {$order->number} confirmed. Invoice {$order->invoice?->number} issued.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
