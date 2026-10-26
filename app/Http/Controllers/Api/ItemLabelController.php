<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemLabel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\SaveItemLabelRequest;
use App\Http\Resources\Items\ItemLabelResource;
use App\Services\Api\Items\ItemLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's item categories and brands - /item-categories and
 * /item-brands, two lists that work the same way (see ItemLabel).
 */
class ItemLabelController extends Controller
{
    public function __construct(
        protected ItemLabelService $itemLabelService,
    ) {}

    public function index(Request $request, ItemLabel $label): JsonResponse
    {
        try {
            return response()->json([
                'data' => ItemLabelResource::collection($this->itemLabelService->list($this->currentWorkspace($request), $label)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveItemLabelRequest $request, ItemLabel $label): JsonResponse
    {
        try {
            $row = $this->itemLabelService->create($this->currentWorkspace($request), $label, $request->validated('name'));

            return response()->json([
                'message' => "{$label->label()} added successfully.",
                'data'    => new ItemLabelResource($row),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveItemLabelRequest $request, ItemLabel $label, int $id): JsonResponse
    {
        try {
            $row = $this->itemLabelService->update($this->currentWorkspace($request), $label, $id, $request->validated('name'));

            return response()->json([
                'message' => "{$label->label()} updated successfully.",
                'data'    => new ItemLabelResource($row),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, ItemLabel $label, int $id): JsonResponse
    {
        try {
            $this->itemLabelService->delete($this->currentWorkspace($request), $label, $id);

            return response()->json([
                'message' => "{$label->label()} deleted successfully.",
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
