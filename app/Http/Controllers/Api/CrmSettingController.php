<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\SaveCrmSettingsRequest;
use App\Http\Resources\Crm\CrmSettingsResource;
use App\Services\Api\Crm\CrmSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > CRM (Doc 17 Phase A1): the mode, and the lists it shows with it. */
class CrmSettingController extends Controller
{
    public function __construct(
        protected CrmSettingService $crmSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmSettingsResource($this->crmSettingService->show($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->crmSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'CRM settings saved.',
                'data'    => new CrmSettingsResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
