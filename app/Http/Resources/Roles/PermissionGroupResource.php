<?php

namespace App\Http\Resources\Roles;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One permission group as a role's page draws it (Permissions::groups): its
 * label, the app it belongs to (null for the Core Workspace), and each
 * permission with its label and what it needs - ticked along with it.
 */
class PermissionGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key'         => $this->resource['key'],
            'label'       => $this->resource['label'],
            'family'      => $this->resource['family'],
            'permissions' => $this->resource['permissions'],
        ];
    }
}
