<?php

namespace App\Http\Resources\AppCategories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'is_active'  => (bool) $this->is_active,
            'created_at' => to_display_timezone_iso($this->created_at),
        ];
    }
}
