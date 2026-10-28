<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Sales: what new sales documents start with. */
class SalesSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_terms'      => $this->payment_terms,
            'quotation_validity' => $this->quotation_validity,
            'proforma_validity'  => $this->proforma_validity,
            'line_discounts'     => $this->line_discounts,
            'document_discounts' => $this->document_discounts,
            'notes'              => $this->notes,
            'terms'              => $this->terms,
        ];
    }
}
