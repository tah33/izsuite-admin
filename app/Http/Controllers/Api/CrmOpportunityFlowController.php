<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmOpportunityStageRequest;
use App\Http\Resources\Crm\CrmOpportunityResource;
use App\Services\Api\Crm\CrmOpportunityFlow;
use Illuminate\Http\JsonResponse;

/** Moving an opportunity to another stage (Doc 17 Phase D1). See CrmOpportunityFlow. */
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
                'message' => "Moved to {$opportunity->stage?->name}.",
                'data'    => new CrmOpportunityResource($opportunity),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
