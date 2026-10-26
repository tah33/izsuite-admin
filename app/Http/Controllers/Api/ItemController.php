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
                $request->only(['search', 'type', 'usage', 'category_id', 'brand_id', 'status', 'for_combo']),
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

    /** One item, whole - for its profile and its edit form. */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ItemResource($this->itemService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        try {
            return $this->setActive($request, $id, true);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        try {
            return $this->setActive($request, $id, false);

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

    /** The answer for activate() and deactivate(), which catch around it. */
    private function setActive(Request $request, int $id, bool $active): JsonResponse
    {
        $item = $this->itemService->setActive($this->currentWorkspace($request), $id, $active);

        return response()->json([
            'message' => $active ? 'Item activated.' : 'Item deactivated.',
            'data'    => new ItemResource($item),
        ]);
    }
}
