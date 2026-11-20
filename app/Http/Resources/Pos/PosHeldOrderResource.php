<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order held at a POS register as its list shows it (Doc 18 Phase D1):
 * its number, customer, items, total when held, who held it and when, and
 * when it expires.
 */
class PosHeldOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lines = $this->cart['lines'] ?? [];

        return [
            'id'         => $this->id,
            'number'     => $this->number,
            'customer'   => $this->contact?->name,
            'items'      => implode(', ', array_column($lines, 'name')),
            'total'      => (string) $this->grand_total,
            'cashier'    => $this->cashier?->name,
            'held_at'    => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
