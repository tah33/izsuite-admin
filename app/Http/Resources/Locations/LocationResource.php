<?php

namespace App\Http\Resources\Locations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A location as Settings > Locations lists and edits it (Doc 12 sections 16
 * and 17) - the list is the only place it is read, so it carries every
 * field - and as the dashboard's location pickers offer it: only an active
 * one for something new.
 */
class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'code'            => $this->code,
            'is_primary'      => $this->is_primary,
            'is_active'       => $this->is_active,
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
        ];
    }
}
