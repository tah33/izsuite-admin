<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmOpportunityLoseRequest;
use App\Http\Requests\Api\Crm\CrmOpportunityReopenRequest;
use App\Http\Requests\Api\Crm\CrmOpportunityWinRequest;
use App\Http\Resources\Crm\CrmOpportunityResource;
use App\Services\Api\Crm\CrmOpportunityClosing;
use Illuminate\Http\JsonResponse;

/** How an opportunity ends (Doc 17 Phase D2a): won, lost, and reopened. See CrmOpportunityClosing. */
class CrmOpportunityClosingController extends Controller
{
    public function __construct(
        protected CrmOpportunityClosing $closing,
    ) {}

    public function win(CrmOpportunityWinRequest $request, int $id): JsonResponse
    {
        try {
            $opportunity = $this->closing->win($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => 'Marked won.',
                'data'    => new CrmOpportunityResource($opportunity),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function lose(CrmOpportunityLoseRequest $request, int $id): JsonResponse
    {
        try {
            $opportunity = $this->closing->lose($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => 'Marked lost.',
                'data'    => new CrmOpportunityResource($opportunity),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reopen(CrmOpportunityReopenRequest $request, int $id): JsonResponse
    {
        try {
            $opportunity = $this->closing->reopen($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => "Reopened at {$opportunity->stage?->name}.",
                'data'    => new CrmOpportunityResource($opportunity),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
