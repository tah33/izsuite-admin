<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A storefront customer's account as they see it (Doc 19 Phase E1): name, e-mail, phone, whether the e-mail is verified, and since when. */
class StoreCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'     => $this->name,
            'email'    => $this->email,
            'phone'    => $this->phone,
            'verified' => $this->email_verified_at !== null,
            'since'    => $this->created_at?->toDateString(),
        ];
    }
}
