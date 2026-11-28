<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListEcommerceAbandonedCartsRequest;
use App\Http\Resources\Ecommerce\EcommerceAbandonedCartResource;
use App\Services\Api\Ecommerce\EcommerceAbandonedCartService;
use Illuminate\Http\JsonResponse;

/** The business's abandoned carts (Doc 19 Phase H3a), under View Orders: the Abandoned Carts page. */
class EcommerceAbandonedCartController extends Controller
{
    public function __construct(
        protected EcommerceAbandonedCartService $carts,
    ) {}

    public function index(ListEcommerceAbandonedCartsRequest $request): JsonResponse
    {
        try {
            $page = $this->carts->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json(['data' => EcommerceAbandonedCartResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
