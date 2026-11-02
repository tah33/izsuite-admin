<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An Activity Log entry, as Settings > Security & Audit lists it: when, who
 * by name (null once nobody is known), what was done, each setting it changed
 * with its previous and new value - sensitive ones masked when written - and
 * the location it is about, by name. Expects user loaded and location_name
 * set (AuditLogService).
 */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'at'          => $this->created_at?->toIso8601String(),
            'user'        => $this->user?->name,
            'description' => $this->description,
            'changes'     => $this->properties['changes'] ?? [],
            'location'    => $this->location_name ?? null,
        ];
    }
}
