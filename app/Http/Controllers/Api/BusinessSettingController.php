<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSettings\SaveBusinessSettingsRequest;
use App\Http\Resources\BusinessSettings\BusinessSettingResource;
use App\Services\Api\BusinessSettings\BusinessSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessSettingController extends Controller
{
    public function __construct(
        protected BusinessSettingService $businessSettingService,
    ) {}

    /**
     * The signed-in account's business settings - `data` is null until they
     * have saved them once, which is not an error: the form simply starts blank.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $settings = $this->businessSettingService->show($request->user());

            return response()->json([
                'data' => $settings ? new BusinessSettingResource($settings) : null,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Create the settings on the first save, overwrite them after that.
     */
    public function save(SaveBusinessSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->businessSettingService->save($request->user(), $request->validated());

            return response()->json([
                'message' => 'Business settings saved successfully.',
                'data'    => new BusinessSettingResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
