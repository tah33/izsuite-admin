<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentAccounts\SavePaymentAccountRequest;
use App\Http\Resources\PaymentAccounts\PaymentAccountResource;
use App\Services\Api\PaymentAccounts\PaymentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    public function __construct(
        protected PaymentAccountService $paymentAccountService,
    ) {}

    /** The business's payment accounts, alphabetical, with their recorded balances. Not paginated: a business has a handful. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => PaymentAccountResource::collection($this->paymentAccountService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** One account with its recorded balance - its page. */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new PaymentAccountResource($this->paymentAccountService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePaymentAccountRequest $request): JsonResponse
    {
        try {
            $account = $this->paymentAccountService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Payment account added successfully.',
                'data'    => new PaymentAccountResource($account),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePaymentAccountRequest $request, int $id): JsonResponse
    {
        try {
            $account = $this->paymentAccountService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => 'Payment account updated successfully.',
                'data'    => new PaymentAccountResource($account),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->paymentAccountService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Payment account deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
