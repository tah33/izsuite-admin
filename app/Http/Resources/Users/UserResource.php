<?php

namespace App\Http\Resources\Users;

use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user, as the screens use them: their name, sign-in e-mail
 * and phone, their profile image, and the language and time zone they chose
 * (Doc 12 section 22) - null for each business's own - and whether two-factor
 * authentication is on, with how many recovery codes are left unused.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // avatar is stored as a disk path; expose an absolute URL instead so
        // the frontend never has to know about the storage layout.
        $avatarPath = app(ImageService::class)->publicUrl($this->avatar);
        $twoFactor  = $this->two_factor_confirmed_at !== null;

        return [
            'first_name'          => $this->first_name,
            'last_name'           => $this->last_name,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'avatar_url'          => $avatarPath ? url($avatarPath) : null,
            'language'            => $this->language,
            'timezone'            => $this->timezone,
            'two_factor'          => $twoFactor,
            'recovery_codes_left' => $twoFactor ? count($this->two_factor_recovery_codes ?? []) : null,
        ];
    }
}
