<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSetup\SaveBusinessTaxesRequest;
use App\Http\Resources\BusinessSetup\BusinessTaxesResource;
use App\Services\Api\Taxes\TaxSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Taxes: whether the business uses taxes - yes, no, or decide later
 * - and its tax options. The rates are at /tax-rates.
 */
class BusinessTaxController extends Controller
{
    public function __construct(
        protected TaxSettingService $taxSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new BusinessTaxesResource($this->taxSettingService->settings($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveBusinessTaxesRequest $request): JsonResponse
    {
        try {
            $taxes = $this->taxSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Tax settings saved successfully.',
                'data'    => new BusinessTaxesResource($taxes),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
