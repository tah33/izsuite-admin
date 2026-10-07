<?php

namespace App\Http\Resources\UserStaff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStaffResource extends JsonResource
{
    /**
     * user_id is left out - it is always the caller. status goes out as the
     * number it is stored as: 1 for active, 0 for inactive.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'address' => $this->address,
            'state'   => $this->state,
            'country' => $this->country,
            'status'  => $this->status,
        ];
    }
}
