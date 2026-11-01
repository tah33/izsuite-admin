<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Expenses\ExpensePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An expense payment's attachments (Doc 10 section 12): a bank slip, a signed
 * voucher, the payee's receipt - counted toward the business's storage,
 * downloaded through GET /files/{id}/link.
 */
class ExpensePaymentAttachmentController extends Controller
{
    public function __construct(
        protected ExpensePaymentService $expensePaymentService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $payment = $this->expensePaymentService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($payment, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadSalesAttachmentsRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $payment   = $this->expensePaymentService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $payment, FileCollection::Attachments, $request->uploads(), $request->field(), $this->expensePaymentService->describe($payment));

            return response()->json([
                'message' => $files->count() === 1 ? 'Attachment added.' : "{$files->count()} attachments added.",
                'data'    => WorkspaceFileResource::collection($files),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id, int $fileId): JsonResponse
    {
        try {
            $payment = $this->expensePaymentService->find($this->currentWorkspace($request), $id);
            $file    = $this->recordFileService->find($payment, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($payment, FileCollection::Attachments, $file, $this->expensePaymentService->describe($payment));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
