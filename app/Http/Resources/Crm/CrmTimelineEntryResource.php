<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmActivity;
use App\Support\SupportAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of a lead's, an opportunity's or a contact's timeline (Doc 17
 * Phase E3), by its kind:
 *
 * - an activity done or cancelled: its type, subject, a call's direction,
 *   the outcome and how long it took, and who ended it;
 * - a note: its words, the people it mentions and its author;
 * - a change or a file: what was done, by whom - izSuite itself when no user
 *   did it - what it was (a stage move, a new owner, won, lost, another
 *   status) and each value it changed;
 * - a sales document: which, its status, and its total for those who see
 *   Sales' values - made, or later sent, issued or confirmed (Phase F1).
 *
 * On a contact's, an activity or a note names the lead or opportunity it is
 * about. Its resource is CrmTimelineService::timeline()'s entry.
 */
class CrmTimelineEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $record = $this['record'];
        $step   = $this['step'] ?? null;
        $entry  = ['key' => "{$this['kind']}-{$record->getKey()}".($step ? "-{$step}" : ''), 'kind' => $this['kind'], 'at' => $this['at']?->toIso8601String()];

        return match ($this['kind']) {
            'activity' => [
                ...$entry,
                'by'               => ($record->status === CrmActivity::COMPLETED ? $record->completer : $record->canceller)?->name,
                'type'             => $record->type?->name ?? 'Activity',
                // izSuite's key for its own types (call, meeting...), which the timeline draws by; a business's own type has none.
                'type_key'         => $record->type?->key,
                'subject'          => $record->subject,
                'status'           => $record->status,
                'direction'        => $record->call_direction,
                'outcome'          => $record->outcome,
                'duration_minutes' => $record->duration_minutes,
                'for'              => $this['for'],
            ],
            'note' => [
                ...$entry,
                'by'       => $record->user?->name ?? 'A former user',
                'text'     => $record->body,
                'mentions' => array_column($record->mentions ?? [], 'name'),
                'for'      => $this['for'],
            ],
            'document' => [
                ...$entry,
                // Who made it; who confirmed a sales order. Sending and issuing name no one.
                'by'       => match ($step) {
                    'made'      => $record->creator?->name,
                    'confirmed' => $record->confirmer?->name,
                    default     => null,
                },
                'step'     => $step,
                'document' => ['kind' => $record->type->kind(), 'id' => $record->id, 'number' => $record->number, 'revision' => $record->revision, 'is_deposit' => (bool) $record->is_deposit],
                'status'   => $record->shown_status,
                'total'    => $this['values'] ? (string) $record->grand_total : null,
                'currency' => $record->currency,
            ],
            default => [
                ...$entry,
                // A support session's work names izSuite support and who (Doc 15 section 15.22).
                'by'      => SupportAccess::doneBy($record->properties) ?? $record->user?->name ?? 'izSuite',
                'system'  => $record->user_id === null && SupportAccess::doneBy($record->properties) === null,
                'text'    => $record->description,
                'mark'    => $this->mark($record->properties ?? []),
                'changes' => $record->properties['changes'] ?? [],
            ],
        };
    }

    /** What a change was, as its entry's properties say: "won", "lost", another "status", a "stage" move or a new "owner". */
    private function mark(array $properties): ?string
    {
        $status = $properties['status'] ?? null;

        return match (true) {
            $status === 'won', $status === 'lost' => $status,
            $status !== null                      => 'status',
            isset($properties['to_stage'])        => 'stage',
            isset($properties['owner_id'])        => 'owner',
            default                               => null,
        };
    }
}
