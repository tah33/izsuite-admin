<?php

namespace App\Http\Resources\Records;

use App\Support\SupportAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of a record's activity timeline (Doc 14 Phase D): when, who -
 * izSuite itself when no user did it - what was done, and each value it
 * changed. RecordActivityService has set the text and the changes as the
 * role may see them.
 */
class ActivityEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'at'          => $this->created_at?->toIso8601String(),
            // A support session's work names izSuite support and who (Doc 15 section 15.22).
            'by'          => SupportAccess::doneBy($this->properties) ?? $this->user?->name ?? 'izSuite',
            'system'      => $this->user_id === null && SupportAccess::doneBy($this->properties) === null,
            'action'      => $this->action,
            'description' => $this->shown_description,
            'changes'     => $this->shown_changes,
        ];
    }
}
