<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListEcommerceAbandonedCartsRequest;
use App\Http\Resources\Ecommerce\EcommerceAbandonedCartResource;
use App\Services\Api\Ecommerce\EcommerceAbandonedCartService;
use App\Services\Api\Ecommerce\EcommerceCartRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's abandoned carts (Doc 19 Phase H3a), under View Orders: the
 * Abandoned Carts page - and (Phase H3b), under Confirm Orders, a cart's
 * recovery e-mail sent.
 */
class EcommerceAbandonedCartController extends Controller
{
    public function __construct(
        protected EcommerceAbandonedCartService $carts,
        protected EcommerceCartRecovery $recovery,
    ) {}

    /** Phase H3b: the cart $id's recovery e-mail, sent to its customer. */
    public function recovery(Request $request, int $id): JsonResponse
    {
        try {
            $cart = $this->recovery->send($this->currentWorkspace($request), $request->user(), $id);

            return response()->json(['message' => "Recovery e-mail sent to {$cart->customer_email}.", 'data' => new EcommerceAbandonedCartResource($cart)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

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
