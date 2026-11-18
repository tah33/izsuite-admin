<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CreateCrmPipelineRequest;
use App\Http\Requests\Api\Crm\SaveCrmPipelineRequest;
use App\Http\Resources\Crm\CrmPipelineResource;
use App\Services\Api\Crm\CrmPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > CRM's pipelines (Doc 17 sections 17.13 and 17.14): one added
 * with the starter stages (Phase D3b), one saved whole with its stages and
 * whom it is for, and one deleted while no opportunity was ever in it.
 */
class CrmPipelineController extends Controller
{
    public function __construct(
        protected CrmPipelineService $crmPipelineService,
    ) {}

    public function store(CreateCrmPipelineRequest $request): JsonResponse
    {
        try {
            $pipeline = $this->crmPipelineService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => "Pipeline \"{$pipeline->name}\" added.", 'data' => new CrmPipelineResource($pipeline)], 201);

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
