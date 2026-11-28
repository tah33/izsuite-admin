<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\AddStoreCartLineRequest;
use App\Http\Requests\Api\Ecommerce\RecoverStoreCartRequest;
use App\Http\Requests\Api\Ecommerce\UpdateStoreCartLineRequest;
use App\Http\Resources\Ecommerce\StoreCartResource;
use App\Models\User\User;
use App\Services\Api\Ecommerce\StorefrontCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A storefront's cart, for its visitor without signing in (Doc 19 Phase D3):
 * known by the token its first product gave back, sent as X-Cart-Token. Read,
 * a product added, a line's quantity changed, or a line removed - each answer
 * the cart as the store sells it now. Its team previewing the store
 * (?preview=1) may fill one too.
 */
class StoreCartController extends Controller
{
    public function __construct(
        protected StorefrontCartService $cartService,
    ) {}

    public function show(Request $request, string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreCartResource($this->cartService->show($code, $this->token($request), $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function addLine(AddStoreCartLineRequest $request, string $code): JsonResponse
    {
        try {
            $cart = $this->cartService->add($code, $this->token($request), $request->validated(), $this->previewer($request));

            return response()->json(['message' => 'Added to your cart.', 'data' => new StoreCartResource($cart)], $cart['token'] ? 201 : 200);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updateLine(UpdateStoreCartLineRequest $request, string $code, int $line): JsonResponse
    {
        try {
            $cart = $this->cartService->update($code, $this->token($request), $line, (string) $request->validated('quantity'), $this->previewer($request));

            return response()->json(['message' => 'Cart updated.', 'data' => new StoreCartResource($cart)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function removeLine(Request $request, string $code, int $line): JsonResponse
    {
        try {
            $cart = $this->cartService->remove($code, $this->token($request), $line, $this->previewer($request));

            return response()->json(['message' => 'Removed from your cart.', 'data' => new StoreCartResource($cart)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase H3b: the cart its recovery e-mail's link names, opened in this browser - its new token given once. */
    public function recover(RecoverStoreCartRequest $request, string $code): JsonResponse
    {
        try {
            $cart = $this->cartService->recover($code, $request->validated('token'), $this->previewer($request));

            return response()->json(['message' => 'Your cart is as you left it.', 'data' => new StoreCartResource($cart)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The visitor's cart token, as their browser keeps it. */
    private function token(Request $request): ?string
    {
        $token = $request->header('X-Cart-Token');

        return is_string($token) && preg_match('/^[A-Za-z0-9]{20,100}$/', $token) ? $token : null;
    }

    private function previewer(Request $request): ?User
    {
        $user = $request->boolean('preview') ? $request->user('sanctum') : null;

        return $user instanceof User ? $user : null;
    }
}
