<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\SaveCrmOptionRequest;
use App\Http\Resources\Crm\CrmOptionResource;
use App\Services\Api\Crm\CrmSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The entries of Settings > CRM's lists (Doc 17 Phase A1): lead sources, lost reasons and activity types. */
class CrmOptionController extends Controller
{
    public function __construct(
        protected CrmSettingService $crmSettingService,
    ) {}

    public function store(SaveCrmOptionRequest $request): JsonResponse
    {
        try {
            $option = $this->crmSettingService->addOption($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Added.', 'data' => new CrmOptionResource($option)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmOptionRequest $request, int $id): JsonResponse
    {
        try {
            $option = $this->crmSettingService->updateOption($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Saved.', 'data' => new CrmOptionResource($option)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->crmSettingService->deleteOption($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
