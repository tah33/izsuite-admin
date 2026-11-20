<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveCatalogCollectionRequest;
use App\Http\Requests\Api\Files\UploadCatalogImageRequest;
use App\Http\Resources\Ecommerce\CatalogCollectionOptionsResource;
use App\Http\Resources\Ecommerce\CatalogCollectionResource;
use App\Models\User\CatalogCollection;
use App\Services\Api\Ecommerce\CatalogCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The business's collections of products (Doc 19 Phase B2): listed, what their form chooses from, made, changed, deleted, and their pictures. */
class CatalogCollectionController extends Controller
{
    public function __construct(
        protected CatalogCollectionService $collectionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            ['collections' => $collections, 'brand_listings' => $brandListings] = $this->collectionService->list($this->currentWorkspace($request));

            return response()->json(['data' => $collections->map(fn (CatalogCollection $collection) => new CatalogCollectionResource($collection, $brandListings))->values()]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new CatalogCollectionOptionsResource($this->collectionService->options($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCatalogCollectionRequest $request): JsonResponse
    {
        try {
            $collection = $this->collectionService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Collection added.', 'data' => new CatalogCollectionResource($collection)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCatalogCollectionRequest $request, int $id): JsonResponse
    {
        try {
            $collection = $this->collectionService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Collection saved.', 'data' => new CatalogCollectionResource($collection)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $collection = $this->collectionService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => "Collection \"{$collection->name}\" deleted."]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storeImage(UploadCatalogImageRequest $request, int $id): JsonResponse
    {
        try {
            $collection = $this->collectionService->addImage($this->currentWorkspace($request), $id, $request->uploads(), $request->field());

            return response()->json(['message' => 'Picture saved.', 'data' => new CatalogCollectionResource($collection)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyImage(Request $request, int $id): JsonResponse
    {
        try {
            $collection = $this->collectionService->removeImage($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Picture removed.', 'data' => new CatalogCollectionResource($collection)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
