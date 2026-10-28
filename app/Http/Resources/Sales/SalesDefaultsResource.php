<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a new sales document starts with (see SalesFormService::defaults()).
 * exchange_rate is null when no rate was entered for the currency by the
 * document's date; the tax ids are null for none.
 */
class SalesDefaultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_date'      => $this->resource['document_date'],
            'due_date'           => $this->resource['due_date'],
            'expiry_date'        => $this->resource['expiry_date'],
            'payment_terms'      => $this->resource['payment_terms'],
            'currency'           => $this->resource['currency'],
            'exchange_rate'      => $this->resource['exchange_rate'],
            'location_id'        => $this->resource['location_id'],
            'notes'              => $this->resource['notes'],
            'terms'              => $this->resource['terms'],
            'price_tax_mode'     => $this->resource['price_tax_mode'],
            'tax_rounding'       => $this->resource['tax_rounding'],
            'taxes_enabled'      => $this->resource['taxes_enabled'],
            'line_discounts'     => $this->resource['line_discounts'],
            'document_discounts' => $this->resource['document_discounts'],
            'default_tax_id'     => $this->resource['default_tax_id'],
            'exempt_tax_id'      => $this->resource['exempt_tax_id'],
        ];
    }
}
