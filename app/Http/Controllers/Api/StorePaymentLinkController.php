<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ecommerce\StorePaymentLinkResource;
use App\Services\Api\Ecommerce\StorefrontPaymentLinkPage;
use Illuminate\Http\JsonResponse;

/** A payment link's page for its customer, by the token it carries, without signing in (Doc 19 Phase F6a). */
class StorePaymentLinkController extends Controller
{
    public function __construct(
        protected StorefrontPaymentLinkPage $linkPage,
    ) {}

    public function show(string $code, string $token): JsonResponse
    {
        try {
            return response()->json(['data' => new StorePaymentLinkResource($this->linkPage->show($code, $token))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
