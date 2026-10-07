<?php

namespace App\Http\Resources\Workspaces;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    /**
     * user_id is left out - it is always the caller. The app and the staff
     * member go out as an id and a name, and nothing else about either.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'app_id'   => $this->app_id,
            'staff_id' => $this->staff_id,

            // Their names travel with the workspace, so a list can show them
            // even for an app that has left the active catalogue or a staff
            // member who has since been made inactive.
            'app'      => $this->whenLoaded('app', fn () => [
                'id'   => $this->app->id,
                'name' => $this->app->name,
            ]),
            'staff'    => $this->whenLoaded('staff', fn () => [
                'id'   => $this->staff->id,
                'name' => $this->staff->name,
            ]),
        ];
    }
}
