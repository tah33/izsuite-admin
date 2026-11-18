<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\AddCrmPipelineRequest;
use App\Http\Requests\Api\Crm\SaveCrmPipelineRequest;
use App\Http\Resources\Crm\CrmPipelineResource;
use App\Services\Api\Crm\CrmPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A pipeline and its stages, saved from Settings > CRM (Doc 17 Phase A2) -
 * and (Phase D3b1) added, made the default and deleted.
 */
class CrmPipelineController extends Controller
{
    public function __construct(
        protected CrmPipelineService $crmPipelineService,
    ) {}

    public function store(AddCrmPipelineRequest $request): JsonResponse
    {
        try {
            $pipeline = $this->crmPipelineService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Pipeline added.', 'data' => new CrmPipelineResource($pipeline)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmPipelineRequest $request, int $id): JsonResponse
    {
        try {
            $pipeline = $this->crmPipelineService->save($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Pipeline saved.', 'data' => new CrmPipelineResource($pipeline)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function makeDefault(Request $request, int $id): JsonResponse
    {
        try {
            $pipeline = $this->crmPipelineService->makeDefault($this->currentWorkspace($request), $id);

            return response()->json(['message' => "{$pipeline->name} is the default pipeline.", 'data' => new CrmPipelineResource($pipeline)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->crmPipelineService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Pipeline deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
