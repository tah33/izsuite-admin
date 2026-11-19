<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SavePosCashCountRequest;
use App\Http\Requests\Api\Pos\SavePosPaymentMethodRequest;
use App\Http\Requests\Api\Pos\SavePosSettingsRequest;
use App\Http\Resources\Pos\PosPaymentMethodResource;
use App\Http\Resources\Pos\PosSettingsResource;
use App\Services\Api\Pos\PosSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → Point of Sale (Doc 18 Phase A1): its defaults, and its payment methods added, changed and deleted. */
class PosSettingController extends Controller
{
    public function __construct(
        protected PosSettingService $posSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosSettingsResource($this->posSettingService->show($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePosSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->posSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'POS settings saved.', 'data' => new PosSettingsResource($settings)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function saveCashCount(SavePosCashCountRequest $request): JsonResponse
    {
        try {
            $settings = $this->posSettingService->saveCashCount($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Closing settings saved.', 'data' => new PosSettingsResource($settings)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storeMethod(SavePosPaymentMethodRequest $request): JsonResponse
    {
        try {
            $method = $this->posSettingService->addMethod($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Payment method added.', 'data' => new PosPaymentMethodResource($method)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updateMethod(SavePosPaymentMethodRequest $request, int $id): JsonResponse
    {
        try {
            $method = $this->posSettingService->updateMethod($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Payment method saved.', 'data' => new PosPaymentMethodResource($method)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyMethod(Request $request, int $id): JsonResponse
    {
        try {
            $this->posSettingService->deleteMethod($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Payment method deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
