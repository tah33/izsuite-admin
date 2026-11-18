<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmOpportunityStageRequest;
use App\Http\Resources\Crm\CrmOpportunityResource;
use App\Services\Api\Crm\CrmOpportunityFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Moving an opportunity to another stage (Doc 17 Phase D1), and archiving or restoring it (Phase D2a). See CrmOpportunityFlow. */
class CrmOpportunityFlowController extends Controller
{
    public function __construct(
        protected CrmOpportunityFlow $flow,
    ) {}

    public function stage(CrmOpportunityStageRequest $request, int $id): JsonResponse
    {
        try {
            $opportunity = $this->flow->changeStage($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                // Phase D3b1: naming the pipeline it went into.
                'message' => "Moved to {$opportunity->stage?->name}".($this->flow->changedPipeline ? " in {$opportunity->pipeline?->name}" : '').'.',
                'data'    => new CrmOpportunityResource($opportunity),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Opportunity archived.',
                'data'    => new CrmOpportunityResource($this->flow->archive($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Opportunity restored.',
                'data'    => new CrmOpportunityResource($this->flow->restore($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
