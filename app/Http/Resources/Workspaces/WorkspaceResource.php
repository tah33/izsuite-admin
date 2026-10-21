<?php

namespace App\Http\Resources\Workspaces;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * user_id is left out - it is always the caller. The plan goes out as its
     * key and name, and nothing else about it. Which apps are on is for
     * GET /navigation and GET /apps-center, scoped to one workspace.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'package' => $this->whenLoaded('package', fn () => $this->package ? [
                'key'  => $this->package->key,
                'name' => $this->package->name,
            ] : null),
        ];
    }
}
