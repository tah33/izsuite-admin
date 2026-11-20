<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A POS sale as its receipt's buttons need it (Doc 18 Phase C4): its number,
 * and how many copies of its receipt were made - after the first, each a
 * reprint - its customer, and its formal invoice's number once it has one
 * (Phase D4).
 */
class PosReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'number' => $this->number,
            'prints' => (int) $this->receipt_prints,
            'contact_id' => $this->contact_id,
            'invoice'    => $this->invoiced_at ? $this->invoice?->number : null,
        ];
    }
}
