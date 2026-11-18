<?php

namespace App\Http\Resources\Notifications;

use App\Models\Shared\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A notification in the bell (Doc 14 section 15 - Phase B1): what happened,
 * the page that shows it, when, and whether it was read.
 *
 * @property UserNotification $resource
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'trigger'    => $this->trigger,
            'title'      => $this->title,
            'body'       => $this->body,
            'link'       => $this->link,
            'read'       => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
