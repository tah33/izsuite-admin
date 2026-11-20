<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadCatalogPicturesRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Ecommerce\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** An online product's pictures (Doc 19 Phase B1): added, up to its limit, and removed. They are public, as a storefront shows them. */
class CatalogPictureController extends Controller
{
    public function __construct(
        protected CatalogService $catalogService,
    ) {}

    public function store(UploadCatalogPicturesRequest $request, int $item): JsonResponse
    {
        try {
            $pictures = $this->catalogService->addPictures($this->currentWorkspace($request), $item, $request->uploads(), $request->field());

            return response()->json([
                'message' => $pictures->count() === 1 ? 'Picture added.' : "{$pictures->count()} pictures added.",
                'data'    => WorkspaceFileResource::collection($pictures),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $item, int $file): JsonResponse
    {
        try {
            $this->catalogService->removePicture($this->currentWorkspace($request), $item, $file);

            return response()->json(['message' => 'Picture removed.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
