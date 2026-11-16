<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmLeadStepRequest;
use App\Http\Resources\Crm\CrmLeadResource;
use App\Services\Api\Crm\CrmLeadFlow;
use Illuminate\Http\JsonResponse;

/** A lead's steps (Doc 17 Phase B1): assign, mark contacted, qualify, unqualify, archive and reopen. */
class CrmLeadFlowController extends Controller
{
    public function __construct(
        protected CrmLeadFlow $crmLeadFlow,
    ) {}

    public function assign(CrmLeadStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->crmLeadFlow->assign($this->currentWorkspace($request), $request->user(), $id, (int) $request->validated('owner_id')), fn ($lead) => "The lead is now {$lead->owner->name}'s.");
    }

    public function contact(CrmLeadStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->crmLeadFlow->contact($this->currentWorkspace($request), $request->user(), $id), fn () => 'The lead is marked contacted.');
    }

    public function qualify(CrmLeadStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->crmLeadFlow->qualify($this->currentWorkspace($request), $request->user(), $id), fn () => 'The lead is qualified.');
    }

    public function unqualify(CrmLeadStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->crmLeadFlow->unqualify($this->currentWorkspace($request), $request->user(), $id, $request->validated('reason')), fn () => 'The lead is marked unqualified.');
    }

    public function archive(CrmLeadStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->crmLeadFlow->archive($this->currentWorkspace($request), $request->user(), $id, $request->validated('reason')), fn () => 'The lead is archived.');
    }

    public function reopen(CrmLeadStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->crmLeadFlow->reopen($this->currentWorkspace($request), $request->user(), $id), fn () => 'The lead is open again.');
    }

    /** The lead as it is now, and what happened to it. */
    private function step(callable $change, callable $done): JsonResponse
    {
        try {
            $lead = $change();

            return response()->json([
                'message' => $done($lead),
                'data'    => new CrmLeadResource($lead),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
