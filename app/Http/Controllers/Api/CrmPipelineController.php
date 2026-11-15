<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\SaveCrmPipelineRequest;
use App\Http\Resources\Crm\CrmPipelineResource;
use App\Services\Api\Crm\CrmPipelineService;
use Illuminate\Http\JsonResponse;

/** A pipeline and its stages, saved from Settings > CRM (Doc 17 Phase A2). */
class CrmPipelineController extends Controller
{
    public function __construct(
        protected CrmPipelineService $crmPipelineService,
    ) {}

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
}
