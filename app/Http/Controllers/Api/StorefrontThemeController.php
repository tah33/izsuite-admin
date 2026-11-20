<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveStorefrontThemeRequest;
use App\Http\Requests\Api\Files\UploadStorefrontPicturesRequest;
use App\Http\Resources\Ecommerce\StorefrontThemeResource;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Ecommerce\StorefrontThemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A storefront's theme and branding (Doc 19 Phase C4a): its draft, its pictures, and publishing or discarding the draft. */
class StorefrontThemeController extends Controller
{
    public function __construct(
        protected StorefrontThemeService $themeService,
    ) {}

    public function show(Request $request, int $storefront): JsonResponse
    {
        try {
            return response()->json(['data' => new StorefrontThemeResource($this->themeService->show($this->currentWorkspace($request), $storefront))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveStorefrontThemeRequest $request, int $storefront): JsonResponse
    {
        try {
            $theme = $this->themeService->saveDraft($this->currentWorkspace($request), $storefront, $request->validated());

            return response()->json(['message' => 'Draft saved.', 'data' => new StorefrontThemeResource($theme)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storePictures(UploadStorefrontPicturesRequest $request, int $storefront): JsonResponse
    {
        try {
            $files = $this->themeService->addPictures($this->currentWorkspace($request), $storefront, $request->uploads(), $request->field());

            return response()->json([
                'message' => $files->count() === 1 ? 'Picture added.' : "{$files->count()} pictures added.",
                'data'    => WorkspaceFileResource::collection($files),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function publish(Request $request, int $storefront): JsonResponse
    {
        try {
            $theme = $this->themeService->publish($this->currentWorkspace($request), $storefront);

            return response()->json(['message' => 'Theme published.', 'data' => new StorefrontThemeResource($theme)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function discard(Request $request, int $storefront): JsonResponse
    {
        try {
            $theme = $this->themeService->discard($this->currentWorkspace($request), $storefront);

            return response()->json(['message' => 'Draft discarded.', 'data' => new StorefrontThemeResource($theme)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
