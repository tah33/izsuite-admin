<?php

namespace App\Http\Resources\Documents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The number the next document would get: "INV-2026-00001". Wraps the string. */
class NextNumberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'next_number' => $this->resource,
        ];
    }
}
