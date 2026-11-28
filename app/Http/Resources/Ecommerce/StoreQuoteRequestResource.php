<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request for a quotation as its customer is told it was sent (Doc 19
 * Phase H4a): its number, and the e-mail the store replies to.
 *
 * @property EcommerceQuoteRequest $resource
 */
class StoreQuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['number' => $this->resource->number, 'email' => $this->resource->customer_email];
    }
}
