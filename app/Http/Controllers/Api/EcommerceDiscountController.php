<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListEcommerceDiscountsRequest;
use App\Http\Requests\Api\Ecommerce\SaveEcommerceDiscountRequest;
use App\Http\Resources\Ecommerce\EcommerceDiscountCategoryResource;
use App\Http\Resources\Ecommerce\EcommerceDiscountContactResource;
use App\Http\Resources\Ecommerce\EcommerceDiscountProductResource;
use App\Http\Resources\Ecommerce\EcommerceDiscountResource;
use App\Services\Api\Ecommerce\EcommerceDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Discounts page (Doc 19 Phase H1), under Manage Discounts: a page of
 * the business's discount codes, one added, changed or deleted - deactivated
 * once used - and the products a discount may be for; from Phase H2b the
 * categories and customers too.
 */
class EcommerceDiscountController extends Controller
{
    public function __construct(
        protected EcommerceDiscountService $discounts,
    ) {}

    public function index(ListEcommerceDiscountsRequest $request): JsonResponse
    {
        try {
            $page = $this->discounts->page($this->currentWorkspace($request), $request->validated('search'), requested_per_page());

            return response()->json(['data' => EcommerceDiscountResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveEcommerceDiscountRequest $request): JsonResponse
    {
        try {
            $discount = $this->discounts->create($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json(['message' => 'Discount '.($discount->code ?? $discount->name).' added.', 'data' => new EcommerceDiscountResource($discount)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveEcommerceDiscountRequest $request, int $id): JsonResponse
    {
        try {
            $discount = $this->discounts->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Discount '.($discount->code ?? $discount->name).' saved.', 'data' => new EcommerceDiscountResource($discount)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $deleted = $this->discounts->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => $deleted ? 'Discount deleted.' : 'The discount was used on orders, so it is kept with them: it is deactivated instead.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function products(ListEcommerceDiscountsRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => EcommerceDiscountProductResource::collection($this->discounts->productChoices($this->currentWorkspace($request), $request->validated('search')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase H2b: the item categories a discount may be for, by name. */
    public function categories(ListEcommerceDiscountsRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => EcommerceDiscountCategoryResource::collection($this->discounts->categoryChoices($this->currentWorkspace($request), $request->validated('search')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase H2b: the customers a discount may be for, by name or e-mail. */
    public function contacts(ListEcommerceDiscountsRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => EcommerceDiscountContactResource::collection($this->discounts->contactChoices($this->currentWorkspace($request), $request->validated('search')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
