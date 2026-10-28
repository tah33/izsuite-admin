<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sales document's totals as the form holds it now (see
 * SalesFormService::calculate()): each line's amounts in the form's order,
 * the totals, and the tax breakdown. Amounts go out as strings to the cent.
 */
class SalesTotalsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lines'            => array_values($this->resource['lines']),
            'subtotal'         => $this->resource['subtotal'],
            'discount_total'   => $this->resource['discount_total'],
            'tax_total'        => $this->resource['tax_total'],
            'grand_total'      => $this->resource['grand_total'],
            'base_grand_total' => $this->resource['base_grand_total'],
            'taxes'            => $this->resource['taxes'],
        ];
    }
}
