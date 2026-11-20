<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveCatalogVariantsRequest;
use App\Http\Resources\Ecommerce\CatalogEntryResource;
use App\Http\Resources\Ecommerce\CatalogVariantItemResource;
use App\Services\Api\Ecommerce\CatalogVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A listing's options and variants (Doc 19 Phase B3): the items it may group, and saving them. */
class CatalogVariantController extends Controller
{
    public function __construct(
        protected CatalogVariantService $variantService,
    ) {}

    public function items(Request $request, int $item): JsonResponse
    {
        try {
            return response()->json(['data' => CatalogVariantItemResource::collection($this->variantService->items($this->currentWorkspace($request), $item))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCatalogVariantsRequest $request, int $item): JsonResponse
    {
        try {
            ['entry' => $entry, 'removed' => $removed] = $this->variantService->save($this->currentWorkspace($request), $item, $request->validated());

            return response()->json(['message' => $removed ? 'Options removed.' : 'Options and variants saved.', 'data' => new CatalogEntryResource($entry)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
