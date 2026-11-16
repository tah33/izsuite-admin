<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadLeadAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Crm\CrmLeadService;
use App\Services\Api\Files\RecordFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A lead's files (Doc 17 Phase B2b) - a brochure it asked for, a business
 * card, its own requirements - for whoever sees the lead; added and removed
 * by whoever may edit leads.
 */
class CrmLeadAttachmentController extends Controller
{
    public function __construct(
        protected CrmLeadService $crmLeadService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $lead = $this->crmLeadService->find($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($lead, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadLeadAttachmentsRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $lead      = $this->crmLeadService->find($workspace, $request->user(), $id);
            $files     = $this->recordFileService->add($workspace, $lead, FileCollection::Attachments, $request->uploads(), $request->field(), $this->crmLeadService->describe($lead));

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
            $lead = $this->crmLeadService->find($this->currentWorkspace($request), $request->user(), $id);
            $file = $this->recordFileService->find($lead, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($lead, FileCollection::Attachments, $file, $this->crmLeadService->describe($lead));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
