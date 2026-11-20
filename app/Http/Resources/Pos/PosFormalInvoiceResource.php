<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A POS sale given its formal invoice (Doc 18 Phase D4): its receipt number, and its invoice's. */
class PosFormalInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'receipt' => $this->number,
            'invoice' => $this->invoice?->number,
        ];
    }
}
