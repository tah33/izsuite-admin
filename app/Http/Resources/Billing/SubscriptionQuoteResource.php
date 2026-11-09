<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a paid plan would come to, shown before the business subscribes (Doc
 * 13 section 16 - Phase D1): its price, the tax on it, and the total.
 *
 * @property array{currency: string, amount: string, tax: array, total: string} $resource
 */
class SubscriptionQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->resource['currency'],
            'amount'   => $this->resource['amount'],
            'tax'      => SubscriptionTaxLineResource::of($this->resource['tax']),
            'total'    => $this->resource['total'],
        ];
    }
}
