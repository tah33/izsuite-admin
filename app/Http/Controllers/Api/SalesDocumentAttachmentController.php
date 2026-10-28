<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Models\User\SalesDocument;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Sales\SalesDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A sales document's attachments (Doc 08 section 4): files kept with it,
 * counted toward the business's storage, downloaded through
 * GET /files/{id}/link. Removing one from a numbered document is logged like
 * any other (Doc 14 section 14.12).
 */
class SalesDocumentAttachmentController extends Controller
{
    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentService->find($this->currentWorkspace($request), DocumentType::fromKind($kind), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($document, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadSalesAttachmentsRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $document  = $this->salesDocumentService->find($workspace, DocumentType::fromKind($kind), $id);
            $files     = $this->recordFileService->add($workspace, $document, FileCollection::Attachments, $request->uploads(), $request->field(), $this->record($document));

            return response()->json([
                'message' => $files->count() === 1 ? 'Attachment added.' : "{$files->count()} attachments added.",
                'data'    => WorkspaceFileResource::collection($files),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, string $kind, int $id, int $fileId): JsonResponse
    {
        try {
            $document = $this->salesDocumentService->find($this->currentWorkspace($request), DocumentType::fromKind($kind), $id);
            $file     = $this->recordFileService->find($document, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($document, FileCollection::Attachments, $file, $this->record($document));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** How the activity log names it. */
    private function record(SalesDocument $document): string
    {
        return $this->salesDocumentService->describe($document);
    }
}
