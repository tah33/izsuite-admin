<?php

namespace App\Http\Resources\Roles;

use App\Models\User\WorkspaceRole;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Http\Request;

/**
 * A role as its page shows it: what the list shows, and the permissions it
 * holds - none listed with full access, which holds them all - and whether
 * it is the one the user holds, which they cannot change (Doc 12 section 22).
 *
 * @mixin WorkspaceRole
 */
class RoleResource extends RoleListResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'permissions' => $this->full_access ? [] : ($this->permissions ?? []),
            // The largest discount it gives (Doc 16 Phase F2).
            'max_discount' => $this->max_discount,
            'yours'       => $this->id === app(WorkspaceAccess::class)->role(),
        ];
    }
}
