<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One email of a document's history (Doc 14 section 14.13): a copy of it or
 * a reminder, to whom, by whom, and whether it went - queued, sent or
 * failed, with why. Expects sender loaded.
 */
class DocumentEmailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'kind'       => $this->kind,
            'to'         => $this->to,
            'cc'         => $this->cc ?? [],
            'subject'    => $this->subject,
            'status'     => $this->status,
            'failure'    => $this->failure,
            'sent_by'    => $this->sender ? trim("{$this->sender->first_name} {$this->sender->last_name}") : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
