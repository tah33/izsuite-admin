<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ecommerce\StoreAccountOrderResource;
use App\Http\Resources\Ecommerce\StoreOrderResource;
use App\Services\Api\Ecommerce\StorefrontCustomerOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A signed-in storefront customer's orders (Doc 19 Phase E2): a page of them, the newest first, and an order's own page. */
class StoreAccountOrderController extends Controller
{
    public function __construct(
        protected StorefrontCustomerOrders $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $page = $this->orders->page($request->attributes->get('storeCustomer'));

            return response()->json(['data' => StoreAccountOrderResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, string $code, string $order): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreOrderResource($this->orders->show($request->attributes->get('storeCustomer'), (int) $order))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
