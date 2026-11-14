<?php

namespace App\Http\Resources\Notifications;

use App\Models\Admin\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A platform announcement as the workspace's banner shows it (Doc 15
 * section 15.21 - Phase F1): its title, message, priority - info,
 * important or emergency - link, and whether it can be dismissed.
 *
 * @property Announcement $resource
 */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->resource->id,
            'title'       => $this->resource->title,
            'message'     => $this->resource->message,
            'priority'    => $this->resource->priority,
            'link_url'    => $this->resource->link_url,
            'link_label'  => $this->resource->link_label,
            'dismissible' => $this->resource->dismissible,
        ];
    }
}
