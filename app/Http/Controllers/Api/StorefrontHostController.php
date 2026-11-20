<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\FindStorefrontHostRequest;
use App\Http\Resources\Ecommerce\StorefrontHostResource;
use App\Services\Api\Ecommerce\StorefrontDomainService;
use Illuminate\Http\JsonResponse;

/**
 * Whether izSuite answers for a name (Doc 19 Phase A3): a storefront's
 * izSuite address or a verified domain - asked by the server before it
 * issues a certificate, and by the storefront for where it is reached. No
 * sign-in: it tells only a storefront's code, status and primary host.
 */
class StorefrontHostController extends Controller
{
    public function __construct(
        protected StorefrontDomainService $domainService,
    ) {}

    public function show(FindStorefrontHostRequest $request): JsonResponse
    {
        try {
            $found = $this->domainService->answersFor($request->validated('domain'));

            return $found
                ? response()->json(['data' => new StorefrontHostResource($found)])
                : response()->json(['message' => 'izSuite has no storefront at this name.'], 404);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
