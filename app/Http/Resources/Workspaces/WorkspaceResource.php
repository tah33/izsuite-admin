<?php

namespace App\Http\Resources\Workspaces;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * `owned` says whether the caller owns it - only an owned one is renamed
     * or deleted - or is one of its users (Doc 12 section 4). The plan goes
     * out as its key and name, and nothing else about it. Which apps are on
     * is for GET /navigation and GET /apps-center, scoped to one workspace.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'owned'   => $this->user_id === $request->user()?->id,
            'package' => $this->whenLoaded('package', fn () => $this->package ? [
                'key'  => $this->package->key,
                'name' => $this->package->name,
            ] : null),
        ];
    }
}
