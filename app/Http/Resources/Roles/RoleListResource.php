<?php

namespace App\Http\Resources\Roles;

use App\Models\User\WorkspaceRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A role as the Roles list shows it: its name and description, whether it
 * allows everything, whether it is on, how many members hold it, and its kind
 * - "owner" (protected), "template" (a default: switched off, never deleted)
 * or "custom" (the business's own). Expects members_count.
 *
 * @mixin WorkspaceRole
 */
class RoleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'kind'        => $this->isOwner() ? 'owner' : ($this->isTemplate() ? 'template' : 'custom'),
            'full_access' => $this->full_access,
            'is_active'   => $this->is_active,
            'members'     => (int) $this->members_count,
        ];
    }
}
