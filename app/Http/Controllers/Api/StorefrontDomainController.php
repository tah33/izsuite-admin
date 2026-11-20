<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ChoosePrimaryDomainRequest;
use App\Http\Requests\Api\Ecommerce\SaveStorefrontDomainRequest;
use App\Http\Resources\Ecommerce\StorefrontDomainResource;
use App\Http\Resources\Ecommerce\StorefrontDomainsResource;
use App\Services\Api\Ecommerce\StorefrontDomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A storefront's domains (Doc 19 Phase A3): listed, added, verified, made primary and removed. */
class StorefrontDomainController extends Controller
{
    public function __construct(
        protected StorefrontDomainService $domainService,
    ) {}

    public function index(Request $request, int $storefront): JsonResponse
    {
        try {
            return response()->json(['data' => new StorefrontDomainsResource($this->domainService->list($this->currentWorkspace($request), $storefront))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveStorefrontDomainRequest $request, int $storefront): JsonResponse
    {
        try {
            $domain = $this->domainService->add($this->currentWorkspace($request), $storefront, $request->validated('host'));

            return response()->json(['message' => "{$domain->host} added. Give it its DNS records, then verify it.", 'data' => new StorefrontDomainResource($domain)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function verify(Request $request, int $storefront, int $domain): JsonResponse
    {
        try {
            $verified = $this->domainService->verify($this->currentWorkspace($request), $storefront, $domain);

            return response()->json(['message' => "{$verified->host} is verified.", 'data' => new StorefrontDomainResource($verified)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function primary(ChoosePrimaryDomainRequest $request, int $storefront): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $domainId  = $request->validated('domain_id');

            $this->domainService->makePrimary($workspace, $storefront, $domainId === null ? null : (int) $domainId);

            return response()->json(['message' => 'Primary domain saved.', 'data' => new StorefrontDomainsResource($this->domainService->list($workspace, $storefront))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $storefront, int $domain): JsonResponse
    {
        try {
            $removed = $this->domainService->remove($this->currentWorkspace($request), $storefront, $domain);

            return response()->json(['message' => "{$removed->host} removed."]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
