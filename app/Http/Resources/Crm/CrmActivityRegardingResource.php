<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The leads, opportunities or contacts an activity can be planned for (Doc 17 Phase E1): each one's name and what tells it apart. */
class CrmActivityRegardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_map(fn (array $row) => ['id' => $row['id'], 'name' => $row['name'], 'detail' => $row['detail']], $this->resource);
    }
}
