<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListStoreProductsRequest;
use App\Http\Requests\Api\Ecommerce\SuggestStoreProductsRequest;
use App\Http\Resources\Ecommerce\StoreCategoryResource;
use App\Http\Resources\Ecommerce\StoreCollectionResource;
use App\Http\Resources\Ecommerce\StoreFacetsResource;
use App\Http\Resources\Ecommerce\StoreHomeResource;
use App\Http\Resources\Ecommerce\StorePageResource;
use App\Http\Resources\Ecommerce\StoreProductCardResource;
use App\Http\Resources\Ecommerce\StoreProductResource;
use App\Http\Resources\Ecommerce\StoreResource;
use App\Http\Resources\Ecommerce\StoreSitemapResource;
use App\Http\Resources\Ecommerce\StoreSuggestionsResource;
use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\User;
use App\Services\Api\Ecommerce\StorefrontCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A storefront as its customers see it, without signing in (Doc 19 Phase C1):
 * the store, its products and a product - and, from Phase C2, its categories'
 * and collections' pages and its search's suggestions. From Phase C4a, any of
 * them asked with ?preview=1 by its team, signed in, previews it. From Phase
 * C4b, its home page as its theme lays it out; from Phase C5a, its pages;
 * from Phase C6a, what its sitemap lists.
 */
class StoreCatalogController extends Controller
{
    /** How many products a page of a store's list shows. */
    private const PER_PAGE = 24;

    public function __construct(
        protected StorefrontCatalogService $storeService,
    ) {}

    public function show(Request $request, string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreResource($this->storeService->store($code, $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C4b: its home page - its theme's sections, each with what it shows. */
    public function home(Request $request, string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreHomeResource($this->storeService->home($code, $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function products(ListStoreProductsRequest $request, string $code): JsonResponse
    {
        try {
            ['products' => $products, 'terms' => $terms] = $this->storeService->products($code, $request->validated(), self::PER_PAGE, $this->previewer($request));

            return response()->json([
                'data'       => array_map(fn (CatalogProduct $product) => new StoreProductCardResource($product, $terms), $products->items()),
                'pagination' => slim_pagination($products),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function product(Request $request, string $code, string $slug): JsonResponse
    {
        try {
            ['product' => $product, 'terms' => $terms] = $this->storeService->product($code, $slug, $this->previewer($request));

            return response()->json(['data' => new StoreProductResource($product, $terms)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C3: what its products within the filters given offer to filter by. */
    public function facets(ListStoreProductsRequest $request, string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreFacetsResource($this->storeService->facets($code, $request->validated(), $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C2: a category's page. */
    public function category(Request $request, string $code, string $slug): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreCategoryResource($this->storeService->category($code, $slug, $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C2: the collections the store shows. */
    public function collections(Request $request, string $code): JsonResponse
    {
        try {
            $collections = $this->storeService->collections($code, $this->previewer($request));

            return response()->json(['data' => $collections->map(fn (CatalogCollection $collection) => new StoreCollectionResource($collection))->values()]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C2: a collection's page. */
    public function collection(Request $request, string $code, string $slug): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreCollectionResource($this->storeService->collection($code, $slug, $this->previewer($request)), true)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C2: what the search suggests as a customer types. */
    public function suggestions(SuggestStoreProductsRequest $request, string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreSuggestionsResource($this->storeService->suggestions($code, $request->validated('search'), $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C5a: a page of the store's - its contact or about page, or a policy - by its link. */
    public function page(Request $request, string $code, string $slug): JsonResponse
    {
        try {
            return response()->json(['data' => new StorePageResource($this->storeService->page($code, $slug, $this->previewer($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C6a: what the store's sitemap lists for search engines - nothing while it may not be listed. */
    public function sitemap(string $code): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreSitemapResource($this->storeService->sitemap($code))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase C4a: who asks to preview the store - signed in, by the token sent - if anyone does. */
    private function previewer(Request $request): ?User
    {
        $user = $request->boolean('preview') ? $request->user('sanctum') : null;

        return $user instanceof User ? $user : null;
    }
}
