<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSetup\SaveBusinessTaxesRequest;
use App\Services\Api\BusinessSetup\BusinessSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Taxes: whether the business uses taxes - yes, no, or decide later.
 */
class BusinessTaxController extends Controller
{
    public function __construct(
        protected BusinessSetupService $businessSetupService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->businessSetupService->taxes($this->currentWorkspace($request)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveBusinessTaxesRequest $request): JsonResponse
    {
        try {
            $taxes = $this->businessSetupService->saveTaxes($this->currentWorkspace($request), $request->validated('tax_mode'));

            return response()->json([
                'message' => 'Tax settings saved successfully.',
                'data'    => $taxes,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
