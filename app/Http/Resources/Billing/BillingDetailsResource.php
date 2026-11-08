<?php

namespace App\Http\Resources\Billing;

use App\Models\User\WorkspaceBilling;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business's billing details for its izSuite subscription (Doc 13 section 11).
 *
 * @mixin WorkspaceBilling
 */
class BillingDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'    => $this->name,
            'email'   => $this->email,
            'address' => $this->address,
            'country' => $this->country,
            'tax_id'  => $this->tax_id,
        ];
    }
}
