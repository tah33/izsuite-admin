<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadItemAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Models\User\Item;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Items\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An item's attachments: documents kept with it - a datasheet, a manual, a
 * supplier's price list - whatever its type, apart from a digital product's
 * files that its customers receive (ItemFileController). Private and counted
 * toward the business's storage; they download through GET /files/{id}/link.
 * Deleting the item deletes them.
 */
class ItemAttachmentController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $item = $this->itemService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($item, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadItemAttachmentsRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $item      = $this->itemService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $item, FileCollection::Attachments, $request->uploads(), $request->field(), $this->record($item));

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
            $item = $this->itemService->find($this->currentWorkspace($request), $id);
            $file = $this->recordFileService->find($item, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($item, FileCollection::Attachments, $file, $this->record($item));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** How the activity log names it. */
    private function record(Item $item): string
    {
        return 'item '.$this->recordFileService->quoted($item->name);
    }
}
