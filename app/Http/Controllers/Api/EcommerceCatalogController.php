<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListCatalogRequest;
use App\Http\Requests\Api\Ecommerce\SaveCatalogProductRequest;
use App\Http\Resources\Ecommerce\CatalogEntryResource;
use App\Http\Resources\Ecommerce\CatalogItemResource;
use App\Models\User\Item;
use App\Services\Api\Ecommerce\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** eCommerce's catalog (Doc 19 Phase B1): the items for sale with their online listings, an item's listing, saving it and taking it out. */
class EcommerceCatalogController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
    ) {}

    public function index(ListCatalogRequest $request): JsonResponse
    {
        try {
            ['items' => $items, 'today' => $today] = $this->catalogService->list($this->currentWorkspace($request), $request->only(['search', 'status', 'category_id']), requested_per_page());

            return response()->json([
                'data'       => array_map(fn (Item $item) => new CatalogItemResource($item, $today), $items->items()),
                'pagination' => slim_pagination($items),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $item): JsonResponse
    {
        try {
            return response()->json(['data' => new CatalogEntryResource($this->catalogService->show($this->currentWorkspace($request), $item))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCatalogProductRequest $request, int $item): JsonResponse
    {
        try {
            $entry = $this->catalogService->save($this->currentWorkspace($request), $item, $request->validated());

            return response()->json(['message' => 'Online product saved.', 'data' => new CatalogEntryResource($entry)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        try {
            $product = $this->catalogService->remove($this->currentWorkspace($request), $item);

            return response()->json(['message' => "\"{$product->title}\" taken out of the catalog."]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
