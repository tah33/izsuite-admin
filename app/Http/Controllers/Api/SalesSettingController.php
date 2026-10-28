<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\SaveSalesSettingsRequest;
use App\Http\Resources\Sales\SalesSettingsResource;
use App\Services\Api\Sales\SalesSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Sales (Doc 11 section 15): what new sales documents start with. */
class SalesSettingController extends Controller
{
    public function __construct(
        protected SalesSettingService $salesSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SalesSettingsResource($this->salesSettingService->settings($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveSalesSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->salesSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Sales settings saved successfully.',
                'data'    => new SalesSettingsResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
