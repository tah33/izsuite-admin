<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmForecastRequest;
use App\Http\Resources\Crm\CrmForecastResource;
use App\Services\Api\Crm\CrmForecastService;
use Illuminate\Http\JsonResponse;

/** The Forecast (Doc 17 section 17.14 - Phase G2). */
class CrmForecastController extends Controller
{
    public function __construct(
        protected CrmForecastService $forecast,
    ) {}

    public function show(CrmForecastRequest $request): JsonResponse
    {
        try {
            $rows = $this->forecast->rows($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json(['data' => new CrmForecastResource($rows)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
