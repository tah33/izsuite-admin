<?php

namespace App\Http\Resources\Profile;

use App\Services\Api\Auth\LoginAsService;
use App\Support\DeviceName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One of a user's signed-in devices, as My Profile lists it (Doc 12 section
 * 22): what it is - null when its browser said nothing known, or it signed in
 * before devices were recorded - where it signed in from, when, and when it
 * was last used; whether it is the device in hand, and whether it is izSuite
 * support signed in as the user.
 *
 * @mixin PersonalAccessToken
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'device'         => DeviceName::from($this->user_agent),
            'ip_address'     => $this->ip_address,
            'signed_in_at'   => $this->created_at?->toIso8601String(),
            'last_active_at' => ($this->last_used_at ?? $this->created_at)?->toIso8601String(),
            'current'        => $this->id === $request->user()?->currentAccessToken()?->id,
            'support'        => $this->name === LoginAsService::TOKEN_NAME,
        ];
    }
}
