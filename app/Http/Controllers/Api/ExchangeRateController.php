<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSetup\SaveExchangeRateRequest;
use App\Http\Resources\BusinessSetup\ExchangeRateResource;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Currencies > Exchange Rates (Doc 11 section 7). */
class ExchangeRateController extends Controller
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
    ) {}

    /** Every rate of the business's other currencies, newest first. Not paginated: a business has a handful. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => ExchangeRateResource::collection($this->exchangeRateService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Adds a rate - or, for a day that already has one, replaces it. */
    public function store(SaveExchangeRateRequest $request): JsonResponse
    {
        try {
            $rate = $this->exchangeRateService->save($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => 'Exchange rate saved successfully.',
                'data'    => new ExchangeRateResource($rate),
            ], $rate->wasRecentlyCreated ? 201 : 200);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->exchangeRateService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Exchange rate removed successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
