<?php

namespace App\Http\Resources\Support;

use App\Models\User\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A support case in the app's list (Doc 15 section 15.22): what it is
 * about, its status, who opened it, when it last moved, and whether izSuite
 * wrote last.
 *
 * @property Ticket $resource
 */
class SupportCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'subject'            => $this->subject,
            'status'             => $this->status,
            'status_label'       => $this->statusLabel(),
            'opened_by'          => $this->user?->name,
            'izsuite_wrote_last' => (bool) $this->latestMessage?->by_staff,
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
