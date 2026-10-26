<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadItemFilesRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Models\User\Item;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Items\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A digital product's files (Doc 04 section 12): what its customers receive,
 * kept private - they download only through a short-lived link (GET
 * /files/{id}/link), never a public address. Only a digital product takes new
 * ones; an item that stops being one keeps those it has.
 */
class ItemFileController extends Controller
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
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($item, FileCollection::DigitalFiles)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadItemFilesRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $item      = $this->itemService->find($workspace, $id);

            $this->itemService->ensureTakesFiles($item);

            $files = $this->recordFileService->add($workspace, $item, FileCollection::DigitalFiles, $request->uploads(), $request->field(), $this->record($item));

            return response()->json([
                'message' => $files->count() === 1 ? 'File added.' : "{$files->count()} files added.",
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
            $file = $this->recordFileService->find($item, FileCollection::DigitalFiles, $fileId);

            $this->recordFileService->remove($item, FileCollection::DigitalFiles, $file, $this->record($item));

            return response()->json([
                'message' => 'File removed.',
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
