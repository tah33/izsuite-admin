<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Purchases\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A purchase's attachments (Doc 09 section 6): the supplier's invoice or
 * receipt and anything else kept with it, counted toward the business's
 * storage, downloaded through GET /files/{id}/link. Never read by OCR unless
 * that app is on.
 */
class PurchaseAttachmentController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $purchase = $this->purchaseService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($purchase, FileCollection::Attachments)),
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
            $purchase  = $this->purchaseService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $purchase, FileCollection::Attachments, $request->uploads(), $request->field(), $this->purchaseService->describe($purchase));

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
            $purchase = $this->purchaseService->find($this->currentWorkspace($request), $id);
            $file     = $this->recordFileService->find($purchase, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($purchase, FileCollection::Attachments, $file, $this->purchaseService->describe($purchase));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
