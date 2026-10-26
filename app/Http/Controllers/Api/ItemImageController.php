<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadItemImageRequest;
use App\Http\Resources\Items\ItemResource;
use App\Models\User\Item;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Items\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An item's image (Doc 04 section 6): one picture, shown on its profile and,
 * as a thumbnail, in the Items list. A new one replaces it. Both answer with
 * the item, so the page shows the change at once.
 */
class ItemImageController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
        protected RecordFileService $recordFileService,
    ) {}

    public function store(UploadItemImageRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $item      = $this->itemService->find($workspace, $id);

            $this->recordFileService->add($workspace, $item, FileCollection::Image, $request->uploads(), $request->field(), $this->record($item));

            return response()->json([
                'message' => 'Image saved.',
                'data'    => new ItemResource($this->itemService->find($workspace, $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $item      = $this->itemService->find($workspace, $id);
            $image     = $item->image ?? abort(404, 'This item has no image.');

            $this->recordFileService->remove($item, FileCollection::Image, $image, $this->record($item));

            return response()->json([
                'message' => 'Image removed.',
                'data'    => new ItemResource($this->itemService->find($workspace, $id)),
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
