<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a new purchase starts with, as the form asks for it (Doc 09 sections
 * 5, 14 and 15): its dates and terms, currency and that day's rate - null when
 * the business has none for it - location and notes, the tax settings it is
 * worked out with, and the tax new lines start with. supplier_treatment is the
 * supplier's own tax treatment when it is not the standard one: their lines
 * then start with supplier_tax_id - null being no tax. Purchases always offer
 * line and document discounts. `supplier` is the contact asked about, when it
 * is an active supplier. Wraps PurchaseFormService::defaults().
 */
class PurchaseDefaultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_date'      => $this->resource['document_date'],
            'due_date'           => $this->resource['due_date'],
            'payment_terms'      => $this->resource['payment_terms'],
            'currency'           => $this->resource['currency'],
            'exchange_rate'      => $this->resource['exchange_rate'],
            'location_id'        => $this->resource['location_id'],
            'notes'              => $this->resource['notes'],
            'price_tax_mode'     => $this->resource['price_tax_mode'],
            'tax_rounding'       => $this->resource['tax_rounding'],
            'taxes_enabled'      => $this->resource['taxes_enabled'],
            'line_discounts'     => true,
            'document_discounts' => true,
            'default_tax_id'     => $this->resource['default_tax_id'],
            'supplier_treatment' => $this->resource['supplier_treatment'],
            'supplier_tax_id'    => $this->resource['supplier_tax_id'],
            'supplier'           => $this->resource['supplier'] ? ['id' => $this->resource['supplier']->id, 'name' => $this->resource['supplier']->name] : null,
        ];
    }
}
