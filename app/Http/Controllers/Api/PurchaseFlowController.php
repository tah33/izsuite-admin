<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchases\RecordPurchaseRequest;
use App\Http\Requests\Api\Purchases\VoidPurchaseRequest;
use App\Http\Resources\Purchases\PurchaseResource;
use App\Models\User\Purchase;
use App\Services\Api\Purchases\PurchaseFlow;
use App\Services\Api\Purchases\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A purchase's status changes (Doc 09 section 10): recording a draft, and voiding a recorded one. */
class PurchaseFlowController extends Controller
{
    public function __construct(
        protected PurchaseFlow $purchaseFlow,
        protected PurchaseService $purchaseService,
    ) {}

    public function record(RecordPurchaseRequest $request, int $id): JsonResponse
    {
        try {
            $purchase = $this->purchaseFlow->record($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return $this->answer($request, $purchase, "Purchase {$purchase->number} recorded.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function void(VoidPurchaseRequest $request, int $id): JsonResponse
    {
        try {
            $purchase = $this->purchaseFlow->void($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return $this->answer($request, $purchase, "Purchase {$purchase->number} voided.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function answer(Request $request, Purchase $purchase, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => new PurchaseResource($purchase, $this->purchaseService->today($this->currentWorkspace($request))),
        ]);
    }
}
