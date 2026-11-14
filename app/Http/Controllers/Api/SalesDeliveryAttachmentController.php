<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Sales\SalesDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A delivery's files: its proof of delivery - a signed note, photos (Doc 16 section 16.10). */
class SalesDeliveryAttachmentController extends Controller
{
    public function __construct(
        protected SalesDeliveryService $salesDeliveryService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $delivery = $this->salesDeliveryService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($delivery, FileCollection::Attachments)),
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
            $delivery  = $this->salesDeliveryService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $delivery, FileCollection::Attachments, $request->uploads(), $request->field(), $this->salesDeliveryService->describe($delivery));

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
            $delivery = $this->salesDeliveryService->find($this->currentWorkspace($request), $id);
            $file     = $this->recordFileService->find($delivery, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($delivery, FileCollection::Attachments, $file, $this->salesDeliveryService->describe($delivery));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
