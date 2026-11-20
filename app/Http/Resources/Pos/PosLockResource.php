<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A register's lock as its cashier sees it (Doc 18 Phase I1), as
 * PosLockController gives it: whether it is locked, and whether they have a
 * PIN to open it with.
 */
class PosLockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'locked'  => (bool) $this->resource['locked'],
            'has_pin' => (bool) $this->resource['has_pin'],
        ];
    }
}
