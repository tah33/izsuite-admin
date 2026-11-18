<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\ReorderCrmAssignmentRulesRequest;
use App\Http\Requests\Api\Crm\SaveCrmAssignmentRuleRequest;
use App\Http\Resources\Crm\CrmAssignmentRuleResource;
use App\Http\Resources\Crm\CrmAssignmentRulesResource;
use App\Services\Api\Crm\CrmAssignmentRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → CRM's lead assignment rules (Doc 17 Phase I1): listed, added, changed, deleted and put in order. */
class CrmAssignmentRuleController extends Controller
{
    public function __construct(
        protected CrmAssignmentRuleService $ruleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new CrmAssignmentRulesResource($this->ruleService->index($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCrmAssignmentRuleRequest $request): JsonResponse
    {
        try {
            $rule = $this->ruleService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Rule added.', 'data' => new CrmAssignmentRuleResource($rule)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmAssignmentRuleRequest $request, int $id): JsonResponse
    {
        try {
            $rule = $this->ruleService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Rule saved.', 'data' => new CrmAssignmentRuleResource($rule)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->ruleService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Rule deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reorder(ReorderCrmAssignmentRulesRequest $request): JsonResponse
    {
        try {
            $rules = $this->ruleService->reorder($this->currentWorkspace($request), $request->validated('ids'));

            return response()->json(['message' => 'Order saved.', 'data' => CrmAssignmentRuleResource::collection($rules)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
