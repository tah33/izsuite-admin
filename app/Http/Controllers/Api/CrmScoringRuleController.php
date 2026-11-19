<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\SaveCrmScoringRuleRequest;
use App\Http\Resources\Crm\CrmScoringRuleResource;
use App\Services\Api\Crm\CrmScoringRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → CRM's lead scoring rules (Doc 17 Phase I2): listed, added, changed and deleted. */
class CrmScoringRuleController extends Controller
{
    public function __construct(
        protected CrmScoringRuleService $ruleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => CrmScoringRuleResource::collection($this->ruleService->index($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCrmScoringRuleRequest $request): JsonResponse
    {
        try {
            $rule = $this->ruleService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Rule added. The open leads are being scored again.', 'data' => new CrmScoringRuleResource($rule)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmScoringRuleRequest $request, int $id): JsonResponse
    {
        try {
            $rule = $this->ruleService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Rule saved. The open leads are being scored again.', 'data' => new CrmScoringRuleResource($rule)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->ruleService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Rule deleted. The open leads are being scored again.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
