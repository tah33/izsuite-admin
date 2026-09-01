<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AppCategories\ListAppCategoryRequest;
use App\Http\Resources\AppCategories\AppCategoryResource;
use App\Services\Api\AppCategories\AppCategoryService;
use Illuminate\Http\JsonResponse;

class AppCategoryController extends Controller
{
    public function __construct(
        protected AppCategoryService $appCategoryService,
    ) {}

    /**
     * List active app categories.
     */
    public function index(ListAppCategoryRequest $request): JsonResponse
    {
        try {
            $categories = $this->appCategoryService->list([
                'search'   => $request->query('search'),
                'per_page' => requested_per_page(),
            ]);

            return response()->json([
                'data'       => AppCategoryResource::collection($categories->items()),
                'pagination' => slim_pagination($categories),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
