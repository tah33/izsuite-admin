<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ecommerce\StoreOrderResource;
use App\Services\Api\Ecommerce\StorefrontOrderPage;
use Illuminate\Http\JsonResponse;

/** An online order's own page for its customer, by its link's key, without signing in (Doc 19 Phase D5). */
class StoreOrderController extends Controller
{
    public function __construct(
        protected StorefrontOrderPage $orderPage,
    ) {}

    public function show(string $code, string $key): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreOrderResource($this->orderPage->show($code, $key))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
