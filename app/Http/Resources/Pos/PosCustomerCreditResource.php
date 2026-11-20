<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What credit a customer holds to pay a POS sale with (Doc 18 Phase D3b), in the register's currency. */
class PosCustomerCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available' => (string) $this->resource,
        ];
    }
}
