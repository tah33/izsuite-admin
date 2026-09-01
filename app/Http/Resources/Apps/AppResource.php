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
            'name'            => $this->name,
            'description'     => $this->description,
            'price'           => (float) $this->price,
            'price_formatted' => format_price((float) $this->price),
            'logo_url'        => $logoPath ? url($logoPath) : null,
            'category'        => $this->category,
            'status'          => $this->status,
            'is_active'       => (bool) $this->is_active,
            'created_at'      => to_display_timezone_iso($this->created_at),
        ];
    }
}
