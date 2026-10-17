<?php

namespace App\Http\Resources\Workspaces;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * user_id is left out - it is always the caller. The app goes out as an id
     * and a name, and nothing else about it.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'app_id' => $this->app_id,

            // The name travels with the workspace, so a list can show it even
            // for an app that has left the active catalogue.
            'app'    => $this->whenLoaded('app', fn () => [
                'id'   => $this->app->id,
                'name' => $this->app->name,
            ]),
        ];
    }
}
