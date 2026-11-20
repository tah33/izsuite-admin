<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ApprovePosActionRequest;
use App\Http\Requests\Api\Pos\ListPosApproversRequest;
use App\Http\Resources\Pos\PosApprovalResource;
use App\Http\Resources\Pos\PosApproversResource;
use App\Services\Api\Pos\PosApprovals;
use Illuminate\Http\JsonResponse;

/** A manager's approval at the cashier's register (Doc 18 Phase I2): who may give it, and giving it. */
class PosApprovalController extends Controller
{
    public function __construct(
        protected PosApprovals $approvals,
    ) {}

    public function approvers(ListPosApproversRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => PosApproversResource::collection($this->approvals->approvers($this->currentWorkspace($request), $request->validated('action')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(ApprovePosActionRequest $request): JsonResponse
    {
        try {
            $approval = $this->approvals->approve($this->currentWorkspace($request), (int) $request->validated('approver_id'), $request->validated('pin'), $request->validated('action'));

            return response()->json(['message' => "Approved by {$approval['approver']->name}.", 'data' => new PosApprovalResource($approval)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
