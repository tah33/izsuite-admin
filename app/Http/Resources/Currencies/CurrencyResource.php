<?php

namespace App\Http\Resources\Currencies;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code'   => $this->code,
            'name'   => $this->name,
            'symbol' => $this->symbol,
        ];
    }
}
