<?php

namespace App\Http\Resources\BusinessSetup;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Taxes: whether the business uses taxes, and its tax options - its
 * default sales and purchase tax by id, null for none - and whether it uses
 * withholding tax.
 */
class BusinessTaxesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tax_mode'                => $this->resource['tax_mode'],
            'price_tax_mode'          => $this->resource['price_tax_mode'],
            'tax_rounding'            => $this->resource['tax_rounding'],
            'default_sales_tax_id'    => $this->resource['default_sales_tax_id'],
            'default_purchase_tax_id' => $this->resource['default_purchase_tax_id'],
            'withholding_tax'         => $this->resource['withholding_tax'],
        ];
    }
}
