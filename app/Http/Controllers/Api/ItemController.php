<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\ListItemRequest;
use App\Http\Requests\Api\Items\SaveItemRequest;
use App\Http\Resources\Items\ItemResource;
use App\Services\Api\Items\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function __construct(
        protected ItemService $itemService,
    ) {}

    /**
     * The business's items, a page at a time - a catalogue can run to
     * thousands - alphabetical, optionally searched and filtered.
     */
    public function index(ListItemRequest $request): JsonResponse
    {
        try {
            $items = $this->itemService->list(
                $this->currentWorkspace($request),
                $request->only(['search', 'type', 'status']),
                requested_per_page(),
            );

            return response()->json([
                'data'       => ItemResource::collection($items->items()),
                'pagination' => slim_pagination($items),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveItemRequest $request): JsonResponse
    {
        try {
            $item = $this->itemService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Item added successfully.',
                'data'    => new ItemResource($item),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->itemService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => 'Item updated successfully.',
                'data'    => new ItemResource($item),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->itemService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Item deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
