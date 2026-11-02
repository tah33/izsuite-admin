<?php

namespace App\Http\Resources\Roles;

use App\Models\User\WorkspaceRole;
use Illuminate\Http\Request;

/**
 * A role as its page shows it: what the list shows, and the permissions it
 * holds - none listed with full access, which holds them all.
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
        ];
    }
}
