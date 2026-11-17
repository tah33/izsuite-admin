<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmOpportunityBoardRequest;
use App\Http\Requests\Api\Crm\CrmOpportunityCalendarRequest;
use App\Http\Resources\Crm\CrmOpportunityBoardResource;
use App\Http\Resources\Crm\CrmOpportunityCalendarResource;
use App\Services\Api\Crm\CrmOpportunityViews;
use Illuminate\Http\JsonResponse;

/**
 * The Opportunities page's board and calendar (Doc 17 Phase D4). Moving a
 * card is a stage move, a win or a loss: CrmOpportunityFlowController's and
 * CrmOpportunityClosingController's.
 */
class CrmOpportunityViewController extends Controller
{
    public function __construct(
        protected CrmOpportunityViews $views,
    ) {}

    public function board(CrmOpportunityBoardRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmOpportunityBoardResource($this->views->board($this->currentWorkspace($request), $request->user(), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function calendar(CrmOpportunityCalendarRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmOpportunityCalendarResource($this->views->calendar($this->currentWorkspace($request), $request->user(), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
