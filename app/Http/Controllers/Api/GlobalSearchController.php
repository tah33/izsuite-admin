<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Search\GlobalSearchRequest;
use App\Http\Resources\Search\SearchResultResource;
use App\Services\Api\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * The header's Global Search (Doc 14 section 16): the business's records a
 * term finds, as far as the user may see them.
 */
class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $globalSearchService,
    ) {}

    public function __invoke(GlobalSearchRequest $request): JsonResponse
    {
        try {
            $results = $this->globalSearchService->search($this->currentWorkspace($request), $request->validated('q'));

            return response()->json([
                'data' => SearchResultResource::collection($results),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
