<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a paid plan would come to, shown before the business subscribes (Doc
 * 13 section 16 - Phase D1): its price, a promotion code's discount (Doc 15
 * Phase C3b), izSuite's credit, the tax on what is left, the total - and
 * what each renewal comes to.
 *
 * @property array{currency: string, amount: string, discount?: string, promotion?: ?string, adjustment?: string, tax: array, total: string, renews_at?: string} $resource
 */
class SubscriptionQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency'   => $this->resource['currency'],
            'amount'     => $this->resource['amount'],
            // A promotion's discount and its code (Doc 15 Phase C3b), before credits and tax
            'discount'   => $this->resource['discount'] ?? '0.00',
            'promotion'  => $this->resource['promotion'] ?? null,
            // izSuite's credit to the business, taken off before tax (Doc 15 Phase C2b)
            'adjustment' => $this->resource['adjustment'] ?? '0.00',
            'tax'        => SubscriptionTaxLineResource::of($this->resource['tax']),
            'total'      => $this->resource['total'],
            // What a renewal comes to: the full price and its tax (section 15.17)
            'renews_at'  => $this->resource['renews_at'] ?? $this->resource['total'],
        ];
    }
}
