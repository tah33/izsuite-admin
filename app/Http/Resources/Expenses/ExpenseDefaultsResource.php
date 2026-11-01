<?php

namespace App\Http\Resources\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a new expense starts with, as the form asks for it (Doc 10 sections
 * 5, 14 and 15): its dates and terms, currency and that day's rate - null when
 * the business has none for it - location and notes, the tax settings it is
 * worked out with, and the tax new lines start with. payee_treatment is the
 * payee contact's own tax treatment when it is not the standard one: their
 * lines then start with payee_tax_id - null being no tax; otherwise a line
 * starts with its category's tax, else default_tax_id. Expenses take no
 * discounts. `payee` is the contact asked about, when it is an active
 * supplier. Wraps ExpenseFormService::defaults().
 */
class ExpenseDefaultsResource extends JsonResource
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
            'line_discounts'     => false,
            'document_discounts' => false,
            'default_tax_id'     => $this->resource['default_tax_id'],
            'payee_treatment'    => $this->resource['payee_treatment'],
            'payee_tax_id'       => $this->resource['payee_tax_id'],
            'payee'              => $this->resource['payee'] ? ['id' => $this->resource['payee']->id, 'name' => $this->resource['payee']->name] : null,
        ];
    }
}
