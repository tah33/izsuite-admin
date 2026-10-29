<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchases\SavePurchaseSettingsRequest;
use App\Http\Resources\Purchases\PurchaseSettingsResource;
use App\Services\Api\Purchases\PurchaseSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Purchases (Doc 11 section 16) - there while Purchases Lite or Purchases is on. */
class PurchaseSettingController extends Controller
{
    public function __construct(
        protected PurchaseSettingService $purchaseSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new PurchaseSettingsResource($this->purchaseSettingService->settings($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePurchaseSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->purchaseSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Purchase settings saved successfully.',
                'data'    => new PurchaseSettingsResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
