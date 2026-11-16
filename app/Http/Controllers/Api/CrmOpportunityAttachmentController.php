<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadOpportunityAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Crm\CrmOpportunityService;
use App\Services\Api\Files\RecordFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An opportunity's files (Doc 17 Phase D2b) - a proposal, the customer's
 * requirements, photos - for whoever sees it; added and removed by whoever
 * may edit opportunities, while it is not archived.
 */
class CrmOpportunityAttachmentController extends Controller
{
    public function __construct(
        protected CrmOpportunityService $opportunityService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $opportunity = $this->opportunityService->find($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($opportunity, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadOpportunityAttachmentsRequest $request, int $id): JsonResponse
    {
        try {
            $workspace   = $this->currentWorkspace($request);
            $opportunity = $this->opportunityService->findForFiles($workspace, $request->user(), $id);
            $files       = $this->recordFileService->add($workspace, $opportunity, FileCollection::Attachments, $request->uploads(), $request->field(), $this->opportunityService->describe($opportunity));

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
            $opportunity = $this->opportunityService->findForFiles($this->currentWorkspace($request), $request->user(), $id);
            $file        = $this->recordFileService->find($opportunity, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($opportunity, FileCollection::Attachments, $file, $this->opportunityService->describe($opportunity));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
