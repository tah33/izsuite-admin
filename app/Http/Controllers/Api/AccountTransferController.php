<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentAccounts\SaveTransferRequest;
use App\Http\Requests\Api\Sales\SalesStatusRequest;
use App\Http\Resources\PaymentAccounts\AccountTransferResource;
use App\Models\User\AccountTransfer;
use App\Services\Api\PaymentAccounts\AccountTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transfers between a business's payment accounts (Doc 05 section 13): one,
 * a new one - a draft or done at once - changing or deleting a draft,
 * completing it, and voiding a completed one.
 */
class AccountTransferController extends Controller
{
    public function __construct(
        protected AccountTransferService $accountTransferService,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new AccountTransferResource($this->accountTransferService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->accountTransferService->create($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => $transfer->status === AccountTransfer::COMPLETED ? 'Transfer completed.' : 'Draft transfer saved.',
                'data'    => new AccountTransferResource($transfer),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveTransferRequest $request, int $id): JsonResponse
    {
        try {
            $transfer = $this->accountTransferService->update($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Draft transfer updated.',
                'data'    => new AccountTransferResource($transfer),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $transfer = $this->accountTransferService->complete($this->currentWorkspace($request), $id, $request->user());

            return response()->json([
                'message' => 'Transfer completed.',
                'data'    => new AccountTransferResource($transfer),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function void(SalesStatusRequest $request, int $id): JsonResponse
    {
        try {
            $transfer = $this->accountTransferService->void($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return response()->json([
                'message' => 'Transfer voided.',
                'data'    => new AccountTransferResource($transfer),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->accountTransferService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Draft transfer deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
