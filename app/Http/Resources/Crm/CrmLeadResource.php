<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Services\Crm\CrmLeadRules;
use App\Services\Crm\LeadQualification;
use App\Services\Crm\RecordFollowUp;
use App\Services\Shared\AllowedActions;
use App\Services\Shared\EditConflicts;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead's page and form (Doc 17 Phase B1): every field, its source, owner,
 * team and location by name, and what the signed-in user may do with it now
 * (edit, assign, contact, qualify, unqualify, archive, reopen). Phase G3a
 * adds its response and follow-up (RecordFollowUp), Phase I2 its score with
 * its factors.
 */
class CrmLeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named     = fn ($record) => $record ? ['id' => $record->id, 'name' => $record->name] : null;
        $workspace = $request->attributes->get('workspace');

        return [
            'id'                     => $this->id,
            'version'                => EditConflicts::version($this->resource),
            'status'                 => $this->status,
            'status_reason'          => $this->status_reason,
            'actions'                => app(AllowedActions::class)->lead(app(CrmLeadRules::class)->actions($this->resource)),
            'name'                   => $this->name,
            'business_name'          => $this->business_name,
            'job_title'              => $this->job_title,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'source'                 => $named($this->source),
            'source_details'         => $this->source_details,
            'owner'                  => $named($this->owner),
            'team'                   => $named($this->team),
            'location'               => $named($this->location),
            'priority'               => $this->priority,
            // Its score and the scoring rules that gave it (Phase I2): null and none while the business scores none.
            'score'                  => $this->score,
            'score_factors'          => $this->score_factors ?? [],
            'estimated_value'        => $this->estimated_value,
            'currency'               => $this->currency,
            'expected_purchase_date' => $this->expected_purchase_date?->toDateString(),
            ...$this->resource->only(CrmLead::ADDRESS_FIELDS),
            'preferred_language'     => $this->preferred_language,
            'tags'                   => $this->tags ?? [],
            'notes'                  => $this->notes,
            // Its qualification and interested items (Phase B2a), and what qualifying it still needs.
            ...$this->resource->only(CrmLead::QUALIFICATION_FIELDS),
            'qualification_missing'  => $workspace instanceof Workspace ? app(LeadQualification::class)->missing($workspace, $this->resource) : [],
            'interested_items'       => $this->interestedItems->map(fn ($item) => [
                'item_id'  => $item->item_id,
                'name'     => $item->name,
                'quantity' => $item->quantity,
            ])->values(),
            // Its latest owner's moment; when it was contacted is in its follow-up (Phase G3a).
            'assigned_at'            => $this->assigned_at?->toIso8601String(),
            'added_by'               => $this->creator?->name,
            'created_at'             => $this->created_at?->toIso8601String(),
            // Its response and follow-up (Phase G3a): first assigned and contacted, last contact, next activity, days without activity.
            'follow_up'              => $workspace instanceof Workspace ? app(RecordFollowUp::class)->lead($workspace, $this->resource, app(WorkspaceAccess::class)->can('crm.activities.view')) : null,
            // What it was converted to (Phase C1).
            'conversion'             => $this->converted_at ? [
                'at'          => $this->converted_at->toIso8601String(),
                'by'          => $this->converter?->name,
                'contact'     => $named($this->contact),
                'opportunity' => $this->opportunity ? [
                    'id'                  => $this->opportunity->id,
                    'name'                => $this->opportunity->name,
                    'stage'               => $this->opportunity->stage?->name,
                    'expected_value'      => $this->opportunity->expected_value,
                    'currency'            => $this->opportunity->currency,
                    'expected_close_date' => $this->opportunity->expected_close_date?->toDateString(),
                ] : null,
            ] : null,
        ];
    }
}
