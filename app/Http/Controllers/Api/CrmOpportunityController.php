<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\ListCrmOpportunitiesRequest;
use App\Http\Requests\Api\Crm\SaveCrmOpportunityRequest;
use App\Http\Resources\Crm\CrmOpportunityListResource;
use App\Http\Resources\Crm\CrmOpportunityOptionsResource;
use App\Http\Resources\Crm\CrmOpportunityResource;
use App\Services\Api\Crm\CrmOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Opportunities (Doc 17 Phase D1): the Opportunities page, what an
 * opportunity's form chooses from, one opportunity, adding one and changing
 * one. Moving one between stages is CrmOpportunityFlowController's.
 */
class CrmOpportunityController extends Controller
{
    public function __construct(
        protected CrmOpportunityService $opportunityService,
    ) {}

    public function index(ListCrmOpportunitiesRequest $request): JsonResponse
    {
        try {
            $opportunities = $this->opportunityService->list($this->currentWorkspace($request), $request->user(), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => CrmOpportunityListResource::collection($opportunities->items()),
                'pagination' => slim_pagination($opportunities),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmOpportunityOptionsResource($this->opportunityService->options($this->currentWorkspace($request), $request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmOpportunityResource($this->opportunityService->show($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCrmOpportunityRequest $request): JsonResponse
    {
        try {
            $opportunity = $this->opportunityService->create($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => 'Opportunity added.',
                'data'    => new CrmOpportunityResource($opportunity),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmOpportunityRequest $request, int $id): JsonResponse
    {
        try {
            $opportunity = $this->opportunityService->update($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => 'Opportunity saved.',
                'data'    => new CrmOpportunityResource($opportunity),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
