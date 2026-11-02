<?php

namespace App\Http\Resources\Members;

use App\Models\User\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The business an accepted invitation joined, for the page to open it.
 *
 * @mixin WorkspaceMember
 */
class JoinedBusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'workspace_id' => $this->workspace_id,
            'name'         => $this->workspace->name,
        ];
    }
}
