<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveCatalogCategoryRequest;
use App\Http\Requests\Api\Files\UploadCatalogImageRequest;
use App\Http\Resources\Ecommerce\CatalogCategoryResource;
use App\Services\Api\Ecommerce\CatalogCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The item categories as the storefronts show them (Doc 19 Phase B2): listed, their online side saved, and their pictures. */
class CatalogCategoryController extends Controller
{
    public function __construct(
        protected CatalogCategoryService $categoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => CatalogCategoryResource::collection($this->categoryService->list($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCatalogCategoryRequest $request, int $category): JsonResponse
    {
        try {
            $saved = $this->categoryService->save($this->currentWorkspace($request), $category, $request->validated());

            return response()->json(['message' => 'Online category saved.', 'data' => new CatalogCategoryResource($saved)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storeImage(UploadCatalogImageRequest $request, int $category): JsonResponse
    {
        try {
            $saved = $this->categoryService->addImage($this->currentWorkspace($request), $category, $request->uploads(), $request->field());

            return response()->json(['message' => 'Picture saved.', 'data' => new CatalogCategoryResource($saved)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyImage(Request $request, int $category): JsonResponse
    {
        try {
            $saved = $this->categoryService->removeImage($this->currentWorkspace($request), $category);

            return response()->json(['message' => 'Picture removed.', 'data' => new CatalogCategoryResource($saved)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
