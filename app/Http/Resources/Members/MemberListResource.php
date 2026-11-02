<?php

namespace App\Http\Resources\Members;

use App\Models\User\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user of the business as the Users list shows it (Doc 12 section 5): name,
 * e-mail, role, status, locations and last sign-in - and whether it is the
 * Business Owner or the user looking. `invitation` says where a pending one
 * stands: live, expired or not_sent (staff from before Users).
 *
 * Expects role, user and locations loaded (WorkspaceMemberRepository).
 *
 * @mixin WorkspaceMember
 */
class MemberListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspace = $request->attributes->get('workspace');

        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'role_id'             => $this->role_id,
            'role'                => $this->role?->name,
            'status'              => $this->status,
            'is_owner'            => $workspace !== null && $this->isOwnerOf($workspace),
            'is_self'             => $this->user_id !== null && $this->user_id === $request->user()?->id,
            'location_access'     => $this->location_access,
            'location_ids'        => $this->locations->pluck('id')->all(),
            'default_location_id' => $this->default_location_id,
            'last_login_at'       => $this->user?->last_login_at?->toIso8601String(),
            'invitation'          => $this->isPending() ? match (true) {
                $this->invitation_expires_at === null => 'not_sent',
                $this->invitationLive()               => 'live',
                default                               => 'expired',
            } : null,
        ];
    }
}
