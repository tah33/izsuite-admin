<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveStorefrontPageRequest;
use App\Http\Resources\Ecommerce\StorefrontPageResource;
use App\Http\Resources\Ecommerce\StorefrontPagesResource;
use App\Models\User\StorefrontPage;
use App\Services\Api\Ecommerce\StorefrontPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A storefront's pages (Doc 19 Phase C5a): its contact and about pages and its
 * four policies, each by its kind - and, from Phase C5b, pages of the
 * business's own, by their ids: added, changed and deleted.
 */
class StorefrontPageController extends Controller
{
    public function __construct(
        protected StorefrontPageService $pageService,
    ) {}

    public function index(Request $request, int $storefront): JsonResponse
    {
        try {
            return response()->json(['data' => new StorefrontPagesResource($this->pageService->index($this->currentWorkspace($request), $storefront))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $storefront, string $page): JsonResponse
    {
        try {
            return response()->json(['data' => new StorefrontPageResource($this->pageService->show($this->currentWorkspace($request), $storefront, $page))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C5b: a page of the business's own added. */
    public function store(SaveStorefrontPageRequest $request, int $storefront): JsonResponse
    {
        try {
            return response()->json(['message' => 'Page added.', 'data' => new StorefrontPageResource($this->pageService->create($this->currentWorkspace($request), $storefront, $request->validated()))], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveStorefrontPageRequest $request, int $storefront, string $page): JsonResponse
    {
        try {
            $saved = $this->pageService->save($this->currentWorkspace($request), $storefront, $page, $request->validated());

            return response()->json([
                'message' => $saved['page']->status === StorefrontPage::PUBLISHED ? 'Page saved and published.' : 'Page saved as a draft.',
                'data'    => new StorefrontPageResource($saved),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C5b: a page of the business's own deleted. */
    public function destroy(Request $request, int $storefront, int $page): JsonResponse
    {
        try {
            $this->pageService->delete($this->currentWorkspace($request), $storefront, $page);

            return response()->json(['message' => 'Page deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
