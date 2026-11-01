<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\SaveExpenseCategoryRequest;
use App\Http\Resources\Expenses\ExpenseCategoryResource;
use App\Services\Api\Expenses\ExpenseCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's expense categories (Doc 10 section 7): listed for Settings >
 * Expenses and the expense form, added from either, changed, switched off or
 * on, and deleted while no expense holds them.
 */
class ExpenseCategoryController extends Controller
{
    public function __construct(
        protected ExpenseCategoryService $expenseCategoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => ExpenseCategoryResource::collection($this->expenseCategoryService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveExpenseCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->expenseCategoryService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => "Category \"{$category->name}\" added.",
                'data'    => new ExpenseCategoryResource($category),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveExpenseCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $category = $this->expenseCategoryService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => "Category \"{$category->name}\" saved.",
                'data'    => new ExpenseCategoryResource($category),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->expenseCategoryService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Category deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
