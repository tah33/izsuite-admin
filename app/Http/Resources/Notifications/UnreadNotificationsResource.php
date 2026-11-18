<?php

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How many of the user's notifications are unread (Doc 14 section 15 -
 * Phase B1): the bell's count.
 *
 * @property int $resource
 */
class UnreadNotificationsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['unread' => $this->resource];
    }
}
