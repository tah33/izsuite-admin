<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BusinessSetup\SavePaymentTermRequest;
use App\Http\Resources\BusinessSetup\PaymentTermResource;
use App\Services\Api\BusinessSetup\PaymentTermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's payment terms (Doc 11 section 14): listed for Settings and
 * every term picker, added, changed, switched off or on, and deleted while
 * nothing holds their days.
 */
class PaymentTermController extends Controller
{
    public function __construct(
        protected PaymentTermService $paymentTermService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => PaymentTermResource::collection($this->paymentTermService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePaymentTermRequest $request): JsonResponse
    {
        try {
            $term = $this->paymentTermService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => "Payment term \"{$term->name}\" added.",
                'data'    => new PaymentTermResource($term),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePaymentTermRequest $request, int $id): JsonResponse
    {
        try {
            $term = $this->paymentTermService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => "Payment term \"{$term->name}\" saved.",
                'data'    => new PaymentTermResource($term),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->paymentTermService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Payment term deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
