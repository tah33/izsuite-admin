<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSettings\SaveRegionalSettingsRequest;
use App\Http\Resources\BusinessSettings\RegionalSettingsResource;
use App\Services\Api\BusinessSettings\RegionalSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Regional & Localization: how the business writes dates, times
 * and numbers, the day its week starts, its paper and its fiscal year start.
 */
class RegionalSettingController extends Controller
{
    public function __construct(
        protected RegionalSettingService $regionalSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new RegionalSettingsResource($this->regionalSettingService->show($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveRegionalSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->regionalSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Regional settings saved.',
                'data'    => new RegionalSettingsResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
