<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\ListCrmLeadsRequest;
use App\Http\Requests\Api\Crm\SaveCrmLeadRequest;
use App\Http\Resources\Crm\CrmLeadListResource;
use App\Http\Resources\Crm\CrmLeadOptionsResource;
use App\Http\Resources\Crm\CrmLeadResource;
use App\Services\Api\Crm\CrmLeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leads (Doc 17 Phase B1): the Leads page, what a lead's form chooses from,
 * one lead, adding one and changing one. Its steps are CrmLeadFlowController's.
 */
class CrmLeadController extends Controller
{
    public function __construct(
        protected CrmLeadService $crmLeadService,
    ) {}

    public function index(ListCrmLeadsRequest $request): JsonResponse
    {
        try {
            $leads = $this->crmLeadService->list($this->currentWorkspace($request), $request->user(), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => CrmLeadListResource::collection($leads->items()),
                'pagination' => slim_pagination($leads),
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
                'data' => new CrmLeadOptionsResource($this->crmLeadService->options($this->currentWorkspace($request), $request->user())),
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
                'data' => new CrmLeadResource($this->crmLeadService->find($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCrmLeadRequest $request): JsonResponse
    {
        try {
            $lead = $this->crmLeadService->create($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => 'Lead added.',
                'data'    => new CrmLeadResource($lead),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmLeadRequest $request, int $id): JsonResponse
    {
        try {
            $lead = $this->crmLeadService->update($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => 'Lead saved.',
                'data'    => new CrmLeadResource($lead),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
