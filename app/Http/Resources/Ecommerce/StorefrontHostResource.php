<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The storefront izSuite answers for a name as (Doc 19 Phase A3): its code,
 * its status, and the host it is to be reached at - the others redirect to
 * it. Nothing of its business beyond that.
 */
class StorefrontHostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'         => $this->resource['storefront']->code,
            'status'       => $this->resource['storefront']->status,
            'primary_host' => $this->resource['primary_host'],
        ];
    }
}
