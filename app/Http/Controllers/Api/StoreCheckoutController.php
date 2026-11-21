<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\PlaceStoreOrderRequest;
use App\Http\Requests\Api\Ecommerce\QuoteStoreCheckoutRequest;
use App\Http\Resources\Ecommerce\StoreCheckoutResource;
use App\Http\Resources\Ecommerce\StoreOrderResource;
use App\Models\User\User;
use App\Services\Api\Ecommerce\StorefrontOrderPlacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A storefront's checkout, for its visitor without signing in (Doc 19 Phase
 * D4), their cart known by X-Cart-Token: what it comes to with the address,
 * delivery and payment chosen so far, and the order placed - once, however
 * often it is sent.
 */
class StoreCheckoutController extends Controller
{
    public function __construct(
        protected StorefrontOrderPlacement $placement,
    ) {}

    public function quote(QuoteStoreCheckoutRequest $request, string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreCheckoutResource($this->placement->review($code, $this->token($request), $request->validated(), $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function place(PlaceStoreOrderRequest $request, string $code): JsonResponse
    {
        try {
            $placed = $this->placement->place($code, $this->token($request) ?? abort(404, 'Your cart has expired. Add the products again.'), $request->validated(), [
                'ip' => $request->ip(), 'agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Order placed.', 'data' => new StoreOrderResource($placed['order'], $placed['key'])], $placed['created'] ? 201 : 200);

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
