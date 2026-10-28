<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\RecordRefundRequest;
use App\Http\Resources\Sales\SalesPaymentResource;
use App\Services\Api\Sales\CreditNoteRefunds;
use Illuminate\Http\JsonResponse;

/**
 * POST /sales/credit-notes/{id}/refunds: money paid back to the customer out
 * of a credit note (Doc 08 section 16). Reversing one is
 * POST /sales/payments/{id}/reverse, like a payment.
 */
class SalesRefundController extends Controller
{
    public function __construct(
        protected CreditNoteRefunds $creditNoteRefunds,
    ) {}

    public function store(RecordRefundRequest $request, int $id): JsonResponse
    {
        try {
            $refund = $this->creditNoteRefunds->refund($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Refund recorded.',
                'data'    => new SalesPaymentResource($refund),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
