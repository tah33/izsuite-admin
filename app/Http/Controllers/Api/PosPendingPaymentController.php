<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\DeclinePosPaymentRequest;
use App\Http\Resources\Pos\PosOrderPaymentResource;
use App\Services\Api\Pos\PosPendingPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pending POS payments (Doc 18 Phase D2): a session's, each confirmed or declined. */
class PosPendingPaymentController extends Controller
{
    public function __construct(
        protected PosPendingPayments $pendingPayments,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => PosOrderPaymentResource::collection($this->pendingPayments->of($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        try {
            $payment = $this->pendingPayments->confirm($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "The {$payment->method_name} payment for {$payment->order->number} is confirmed.",
                'data'    => new PosOrderPaymentResource($payment),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function decline(DeclinePosPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->pendingPayments->decline($this->currentWorkspace($request), $id, trim($request->validated('reason')));

            return response()->json([
                'message' => "The {$payment->method_name} payment for {$payment->order->number} is declined: its invoice still owes it.",
                'data'    => new PosOrderPaymentResource($payment),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
