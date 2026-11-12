<?php

namespace App\Http\Resources\Locations;

use App\Services\Shared\EditConflicts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A location as Settings > Locations lists and edits it (Doc 12 sections 16
 * and 17) - the list is the only place it is read, so it carries every
 * field, and how many users may use it - and as the dashboard's location
 * pickers offer it: only an active one for something new.
 */
class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            // When it was last changed: sent back as If-Match when it is saved (Doc 14 section 22).
            'version' => EditConflicts::version($this->resource),
            'name'            => $this->name,
            'code'            => $this->code,
            'is_primary'      => $this->is_primary,
            'is_active'       => $this->is_active,
            'users_count'     => $this->users_count,
            'phone'           => $this->phone,
            'email'           => $this->email,
            'address_line_1'  => $this->address_line_1,
            'address_line_2'  => $this->address_line_2,
            'city'            => $this->city,
            'state'           => $this->state,
            'postal_code'     => $this->postal_code,
            'country'         => $this->country,
            'timezone'        => $this->timezone,
            'document_header' => $this->document_header,
            // Who added it and who last changed it (Doc 14 section 14.5), where it was read with them
            'authors' => $this->whenLoaded('creator', fn () => [
                'added_by'   => $this->creator?->name,
                'added_at'   => $this->created_at?->toIso8601String(),
                'changed_by' => $this->updater?->name,
                'changed_at' => $this->updated_at?->toIso8601String(),
            ]),
        ];
    }
}
