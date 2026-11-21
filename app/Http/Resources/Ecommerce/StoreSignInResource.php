<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront customer's account after signing up, verifying, signing in or
 * a new password (Doc 19 Phase E1): its sign-in's token - given once; none
 * while its e-mail waits to be verified - and the account.
 */
class StoreSignInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token'    => $this->resource['token'],
            'verify'   => $this->resource['token'] === null,
            'customer' => (new StoreCustomerResource($this->resource['customer']))->resolve($request),
        ];
    }
}
