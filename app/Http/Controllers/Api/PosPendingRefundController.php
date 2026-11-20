<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\DeclinePosPaymentRequest;
use App\Http\Resources\Pos\PosPendingRefundResource;
use App\Services\Api\Pos\PosPendingRefunds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pending POS refunds (Doc 18 Phase E2): a session's, each confirmed or declined. */
class PosPendingRefundController extends Controller
{
    public function __construct(
        protected PosPendingRefunds $pendingRefunds,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => PosPendingRefundResource::collection($this->pendingRefunds->of($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $return = $this->pendingRefunds->confirm($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "The {$return->refund_method_name} refund for {$return->number} is confirmed.",
                'data'    => new PosPendingRefundResource($return),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function decline(DeclinePosPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $return = $this->pendingRefunds->decline($this->currentWorkspace($request), $id, trim($request->validated('reason')));

            return response()->json([
                'message' => "The {$return->refund_method_name} refund for {$return->number} is declined: its credit stays on the credit note.",
                'data'    => new PosPendingRefundResource($return),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
