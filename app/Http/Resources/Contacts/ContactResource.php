<?php

namespace App\Http\Resources\Contacts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Every field, flat, as the table holds it: countries as codes, a default
     * currency of null meaning the base currency, payment terms in days, and
     * a shipping address of nulls when it is the billing one. `in_use` says a
     * sales document names it, so it can only be deactivated, not deleted
     * (Doc 03 section 13); one just made is not.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'contact_number'           => $this->contact_number,
            'contact_type'             => $this->contact_type,
            'role'                     => $this->role,
            'name'                     => $this->name,
            'trading_name'             => $this->trading_name,
            'contact_person'           => $this->contact_person,
            'job_title'                => $this->job_title,
            'phone'                    => $this->phone,
            'alt_phone'                => $this->alt_phone,
            'email'                    => $this->email,
            'website'                  => $this->website,
            'preferred_language'       => $this->preferred_language,
            'tags'                     => $this->tags ?? [],
            'billing_line1'            => $this->billing_line1,
            'billing_line2'            => $this->billing_line2,
            'billing_city'             => $this->billing_city,
            'billing_state'            => $this->billing_state,
            'billing_postal_code'      => $this->billing_postal_code,
            'billing_country'          => $this->billing_country,
            'shipping_same_as_billing' => $this->shipping_same_as_billing,
            'shipping_line1'           => $this->shipping_line1,
            'shipping_line2'           => $this->shipping_line2,
            'shipping_city'            => $this->shipping_city,
            'shipping_state'           => $this->shipping_state,
            'shipping_postal_code'     => $this->shipping_postal_code,
            'shipping_country'         => $this->shipping_country,
            'sales_currency'           => $this->sales_currency,
            'customer_payment_terms'   => $this->customer_payment_terms,
            'purchase_currency'        => $this->purchase_currency,
            'supplier_payment_terms'   => $this->supplier_payment_terms,
            'tax_number'               => $this->tax_number,
            'tax_country'              => $this->tax_country,
            'tax_treatment'            => $this->tax_treatment?->value,
            'tax_exemption_number'     => $this->tax_exemption_number,
            'notes'                    => $this->notes,
            'is_active'                => $this->is_active,
            'in_use'                   => (bool) ($this->in_use ?? false),
        ];
    }
}
