<?php

namespace App\Http\Resources\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A category or a brand, and how many of the business's items carry it. */
class ItemLabelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
