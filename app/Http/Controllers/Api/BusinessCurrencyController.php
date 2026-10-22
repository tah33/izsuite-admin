<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSetup\SaveBusinessCurrenciesRequest;
use App\Services\Api\BusinessSetup\BusinessSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Currencies: the business's base currency, and the other
 * currencies it takes or pays in.
 */
class BusinessCurrencyController extends Controller
{
    public function __construct(
        protected BusinessSetupService $businessSetupService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->businessSetupService->currencies($this->currentWorkspace($request)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveBusinessCurrenciesRequest $request): JsonResponse
    {
        try {
            $currencies = $this->businessSetupService->saveCurrencies($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Currencies saved successfully.',
                'data'    => $currencies,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
