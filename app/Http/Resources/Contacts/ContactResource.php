<?php

namespace App\Http\Resources\Contacts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'is_customer'  => $this->is_customer,
            'is_vendor'    => $this->is_vendor,
            'name'         => $this->name,
            'company_name' => $this->company_name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'address'      => $this->address,
            'city'         => $this->city,
            'state'        => $this->state,
            'country'      => $this->country,
            'postal_code'  => $this->postal_code,
            'tax_number'   => $this->tax_number,
            'notes'        => $this->notes,
            'is_active'    => $this->is_active,
        ];
    }
}
