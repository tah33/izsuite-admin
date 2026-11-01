<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Purchases\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A supplier payment's attachments (Doc 09 section 12): a bank slip, a signed
 * voucher, the supplier's receipt - counted toward the business's storage,
 * downloaded through GET /files/{id}/link.
 */
class SupplierPaymentAttachmentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $supplierPaymentService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $payment = $this->supplierPaymentService->find($this->currentWorkspace($request), $id);

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
            $payment   = $this->supplierPaymentService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $payment, FileCollection::Attachments, $request->uploads(), $request->field(), $this->supplierPaymentService->describe($payment));

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
            $payment = $this->supplierPaymentService->find($this->currentWorkspace($request), $id);
            $file    = $this->recordFileService->find($payment, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($payment, FileCollection::Attachments, $file, $this->supplierPaymentService->describe($payment));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
