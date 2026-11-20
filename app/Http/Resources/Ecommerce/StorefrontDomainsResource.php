<?php

namespace App\Http\Resources\Ecommerce;

use App\Services\Api\Ecommerce\StorefrontDomainService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's domains (Doc 19 Phase A3): its izSuite address, the host it
 * is reached at - its primary domain, else that address - where the
 * business's own domains point (a CNAME record), and those domains.
 */
class StorefrontDomainsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storefront = $this->resource['storefront'];
        $domains    = $this->resource['domains'];
        $address    = StorefrontDomainService::addressOf($storefront);

        return [
            'address'      => $address,
            'primary_host' => $domains->firstWhere('is_primary', true)?->host ?? $address,
            'cname_target' => config('ecommerce.cname_target'),
            'domains'      => StorefrontDomainResource::collection($domains)->resolve($request),
        ];
    }
}
