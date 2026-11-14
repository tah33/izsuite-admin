<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Sales\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A customer return's files - photos of the goods, the customer's papers (Doc 16 section 16.17). */
class SalesReturnAttachmentController extends Controller
{
    public function __construct(
        protected SalesReturnService $salesReturnService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $return = $this->salesReturnService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($return, FileCollection::Attachments)),
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
            $return    = $this->salesReturnService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $return, FileCollection::Attachments, $request->uploads(), $request->field(), $this->salesReturnService->describe($return));

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
            $return = $this->salesReturnService->find($this->currentWorkspace($request), $id);
            $file     = $this->recordFileService->find($return, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($return, FileCollection::Attachments, $file, $this->salesReturnService->describe($return));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
