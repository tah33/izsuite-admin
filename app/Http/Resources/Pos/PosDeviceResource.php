<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A register's device as its list shows it (Doc 18 Phase I3). */
class PosDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'name'           => $this->name,
            'connection'     => $this->connection,
            'address'        => $this->address,
            'is_active'      => (bool) $this->is_active,
            'last_used_at'   => $this->last_used_at?->toIso8601String(),
            'last_tested_at' => $this->last_tested_at?->toIso8601String(),
        ];
    }
}
