<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\SaveItemSettingsRequest;
use App\Http\Resources\Items\ItemSettingsResource;
use App\Services\Api\Items\ItemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Items: whether the business uses Brand Management. Its
 * categories and brands themselves are at /item-categories and /item-brands.
 */
class ItemSettingController extends Controller
{
    public function __construct(
        protected ItemSettingService $itemSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ItemSettingsResource($this->itemSettingService->settings($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveItemSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->itemSettingService->save($this->currentWorkspace($request), $request->boolean('brands_enabled'));

            return response()->json([
                'message' => 'Item settings saved successfully.',
                'data'    => new ItemSettingsResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
