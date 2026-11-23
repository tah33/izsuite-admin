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
            // Doc 19 Phase F1: an online customer's doing (ActivityLogService::recordCustomer) names them as the customer.
            'by'          => SupportAccess::doneBy($this->properties) ?? $this->user?->name ?? ($this->byCustomer() ? 'The customer' : 'izSuite'),
            'system'      => $this->user_id === null && SupportAccess::doneBy($this->properties) === null && ! $this->byCustomer(),
            'action'      => $this->action,
            'description' => $this->shown_description,
            'changes'     => $this->shown_changes,
        ];
    }

    private function byCustomer(): bool
    {
        return $this->user_id === null && (bool) ($this->properties['customer'] ?? false);
    }
}
