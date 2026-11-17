<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A CRM activity (Doc 17 Phase E1), as a list's row and its forms show it:
 * its type - and whether completing one needs an outcome - its subject, the
 * record it is for (its opportunity, once a lead's went into one), whom it
 * is given to, when it is due in the business's time zone and whether that
 * has passed, its details, and how it ended: completed with its outcome, or
 * cancelled, when and by whom.
 * Expects due_on, due_time and overdue set (CrmActivityService::mark()).
 */
class CrmActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $regarding = $this->regarding();

        return [
            'id'               => $this->id,
            'type'             => $this->type ? ['id' => $this->type->id, 'name' => $this->type->name, 'needs_outcome' => (bool) $this->type->needs_outcome] : null,
            'subject'          => $this->subject,
            'regarding'        => $regarding ? ['kind' => $this->regardingKind(), 'id' => $regarding->id, 'name' => $regarding->name] : null,
            'assigned_to'      => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null,
            'due_on'           => $this->due_on,
            'due_time'         => $this->due_time,
            'overdue'          => (bool) $this->overdue,
            // Phase E2: minutes before it is due its assignee is reminded; null, none.
            'remind_minutes'   => $this->remind_minutes,
            'duration_minutes' => $this->duration_minutes,
            'priority'         => $this->priority,
            'place'            => $this->place,
            'call_direction'   => $this->call_direction,
            'description'      => $this->description,
            'status'           => $this->status,
            'outcome'          => $this->outcome,
            'completed_at'     => $this->completed_at?->toIso8601String(),
            'completed_by'     => $this->completer?->name,
            'cancelled_at'     => $this->cancelled_at?->toIso8601String(),
            'cancelled_by'     => $this->canceller?->name,
        ];
    }
}
