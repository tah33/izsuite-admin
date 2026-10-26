<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadContactAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Models\User\Contact;
use App\Services\Api\Contacts\ContactService;
use App\Services\Api\Files\RecordFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A contact's attachments (Doc 03 sections 5 and 9): documents kept with it,
 * counted toward the business's storage. They download through
 * GET /files/{id}/link.
 */
class ContactAttachmentController extends Controller
{
    public function __construct(
        protected ContactService $contactService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $contact = $this->contactService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($contact, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadContactAttachmentsRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $contact   = $this->contactService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $contact, FileCollection::Attachments, $request->uploads(), $request->field(), $this->record($contact));

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
            $contact = $this->contactService->find($this->currentWorkspace($request), $id);
            $file    = $this->recordFileService->find($contact, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($contact, FileCollection::Attachments, $file, $this->record($contact));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** How the activity log names it. */
    private function record(Contact $contact): string
    {
        return 'contact '.$this->recordFileService->quoted($contact->name);
    }
}
