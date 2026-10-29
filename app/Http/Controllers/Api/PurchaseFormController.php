<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\CalculateSalesRequest;
use App\Http\Requests\Api\Sales\SalesDefaultsRequest;
use App\Http\Resources\Purchases\PurchaseDefaultsResource;
use App\Http\Resources\Sales\SalesTotalsResource;
use App\Services\Api\Purchases\PurchaseFormService;
use Illuminate\Http\JsonResponse;

/**
 * What the purchase form asks the server (Doc 09 sections 5 and 7): what a
 * new purchase starts with - following the supplier, the date or the currency
 * chosen - and its totals as it is filled in, from the same arithmetic as a
 * sale's.
 */
class PurchaseFormController extends Controller
{
    public function __construct(
        protected PurchaseFormService $purchaseFormService,
    ) {}

    public function defaults(SalesDefaultsRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new PurchaseDefaultsResource($this->purchaseFormService->defaults($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function calculate(CalculateSalesRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SalesTotalsResource($this->purchaseFormService->calculate($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
