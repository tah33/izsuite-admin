<?php

namespace App\Http\Resources\Apps;

use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // logo_url is stored as a disk path; expose an absolute URL instead so
        // API consumers do not have to know where the public disk is mounted.
        $logoPath = app(ImageService::class)->publicUrl($this->logo_url);

        return [
            'id'              => $this->id,
            'app_key'         => $this->app_key,
            'name'            => $this->name,
            'description'     => $this->description,
            'price'           => (float) $this->price,
            'price_formatted' => format_price((float) $this->price),
            'logo_url'        => $logoPath ? url($logoPath) : null,
            'icon'            => $this->icon,
            'category_id'     => $this->category_id,
            'category'        => $this->category?->name,
            'status'          => $this->status,
            'is_active'       => (bool) $this->is_active,
            'family_key'      => $this->family_key,
            'edition'         => $this->edition?->value,
            'edition_label'   => $this->editionLabel(),
            'sort_order'      => (int) $this->sort_order,
            'created_at'      => to_display_timezone_iso($this->created_at),
        ];
    }
}
