<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveStorefrontRequest;
use App\Http\Resources\Ecommerce\StorefrontOptionsResource;
use App\Http\Resources\Ecommerce\StorefrontResource;
use App\Services\Api\Ecommerce\StorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Online storefronts (Doc 19 Phase A1): the Storefronts page, what a storefront's form chooses from, and adding, changing and deleting one. */
class StorefrontController extends Controller
{
    public function __construct(
        protected StorefrontService $storefrontService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => StorefrontResource::collection($this->storefrontService->index($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new StorefrontOptionsResource($this->storefrontService->options($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveStorefrontRequest $request): JsonResponse
    {
        try {
            $storefront = $this->storefrontService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Storefront added.', 'data' => new StorefrontResource($storefront)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveStorefrontRequest $request, int $id): JsonResponse
    {
        try {
            $storefront = $this->storefrontService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Storefront saved.', 'data' => new StorefrontResource($storefront)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->storefrontService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Storefront deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
