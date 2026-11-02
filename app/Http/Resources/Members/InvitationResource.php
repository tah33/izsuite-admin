<?php

namespace App\Http\Resources\Members;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation as its link's page shows it, to anyone holding the link: the
 * business, who sent it, the role it brings, who it is for - and whether that
 * e-mail has an account to sign in with, or makes one.
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $member = $this->resource['member'];

        return [
            'business'       => $member->workspace->name,
            'invited_by'     => $member->inviter?->name,
            'role'           => $member->role->name,
            'name'           => $member->name,
            'email'          => $member->email,
            'account_exists' => $this->resource['account_exists'],
            'expires_at'     => $member->invitation_expires_at?->toIso8601String(),
        ];
    }
}
