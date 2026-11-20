<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveEcommerceSettingsRequest;
use App\Http\Resources\Ecommerce\EcommerceSettingsResource;
use App\Services\Api\Ecommerce\EcommerceSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → eCommerce (Doc 19 Phase A1): the business's own rules for its storefronts. */
class EcommerceSettingController extends Controller
{
    public function __construct(
        protected EcommerceSettingService $settingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new EcommerceSettingsResource($this->settingService->show($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveEcommerceSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->settingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'eCommerce settings saved.', 'data' => new EcommerceSettingsResource($settings)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
