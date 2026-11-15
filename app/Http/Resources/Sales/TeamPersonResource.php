<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One of the business's people a sales team can have as a member or its manager (Doc 17 Phase A2). */
class TeamPersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->resource['id'],
            'name' => $this->resource['name'],
        ];
    }
}
