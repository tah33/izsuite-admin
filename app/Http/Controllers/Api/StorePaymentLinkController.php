<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ReportStorePaymentRequest;
use App\Http\Resources\Ecommerce\StorePaymentLinkResource;
use App\Services\Api\Ecommerce\StorefrontPaymentLinkPage;
use App\Services\Api\Ecommerce\StorefrontPaymentLinkReport;
use Illuminate\Http\JsonResponse;

/**
 * A payment link's page for its customer, by the token it carries, without
 * signing in (Doc 19 Phase F6a) - and (Phase F6b) the customer saying they
 * paid through it.
 */
class StorePaymentLinkController extends Controller
{
    public function __construct(
        protected StorefrontPaymentLinkPage $linkPage,
        protected StorefrontPaymentLinkReport $linkReport,
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

    public function report(ReportStorePaymentRequest $request, string $code, string $token): JsonResponse
    {
        try {
            $page = $this->linkReport->report($code, $token, $request->validated());

            return response()->json(['message' => 'Thank you: we have your payment, and will e-mail you once it is confirmed.', 'data' => new StorePaymentLinkResource($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
