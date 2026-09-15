<?php

namespace App\Http\Resources\Users;

use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // avatar is stored as a disk path; expose an absolute URL instead so
        // the frontend never has to know about the storage layout.
        $avatarPath = app(ImageService::class)->publicUrl($this->avatar);

        return [
            'id'                => $this->id,
            'prefix'            => $this->prefix,
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'username'          => $this->username,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'headline'          => $this->headline,
            'bio'               => $this->bio,
            'avatar_url'        => $avatarPath ? url($avatarPath) : null,

            // role_id is nullable, so a loaded relation can still be null.
            'role'              => $this->relationLoaded('role') && $this->role
                ? [
                    'id'   => $this->role->id,
                    'name' => $this->role->name,
                    'slug' => $this->role->slug,
                ]
                : null,

            'status'            => $this->status,
            'timezone'          => $this->timezone,
            'currency'          => $this->currency,

            'is_email_verified' => ! is_null($this->email_verified_at),
            'email_verified_at' => to_display_timezone_iso($this->email_verified_at),
            'last_login_at'     => to_display_timezone_iso($this->last_login_at),
            'created_at'        => to_display_timezone_iso($this->created_at),
        ];
    }
}
