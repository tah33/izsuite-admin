<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmStage;
use App\Models\User\Workspace;
use App\Services\Crm\CrmOpportunityRules;
use App\Services\Crm\RecordFollowUp;
use App\Services\Shared\AllowedActions;
use App\Services\Shared\EditConflicts;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An opportunity's page and form (Doc 17 Phase D1): every field, its contact,
 * owner, team, location and source by name, its value and weighted value -
 * in its currency and the base one - the pipeline's stages and each stage it
 * has been at, and what the signed-in user may do with it now (edit, stage).
 * Phase G3a adds its follow-up (RecordFollowUp).
 */
class CrmOpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named     = fn ($record) => $record ? ['id' => $record->id, 'name' => $record->name] : null;
        $workspace = $request->attributes->get('workspace');

        return [
            'id'                  => $this->id,
            'version'             => EditConflicts::version($this->resource),
            'status'              => $this->status,
            'actions'             => app(AllowedActions::class)->opportunity(app(CrmOpportunityRules::class)->actions($this->resource)),
            // How it ended (Phase D2a): won or lost on a day, by someone - and why, against whom, and when to follow up.
            'outcome'             => $this->closed_on ? [
                'on'           => $this->closed_on->toDateString(),
                'by'           => $this->closer?->name,
                'won_reason'   => $this->won_reason,
                'lost_reason'  => $named($this->lostReason),
                'competitor'   => $this->lost_competitor,
                'notes'        => $this->lost_notes,
                'follow_up_on' => $this->follow_up_on?->toDateString(),
            ] : null,
            'archived'            => $this->archived_at ? ['at' => $this->archived_at->toIso8601String(), 'by' => $this->archiver?->name] : null,
            'name'                => $this->name,
            'contact'             => $this->contact ? [...$named($this->contact), 'contact_number' => $this->contact->contact_number] : null,
            'contact_person'      => $this->contact_person,
            'lead'                => $named($this->lead),
            // Phase D3b: the pipeline it is in.
            'pipeline'            => $this->pipeline ? $named($this->pipeline) : null,
            'stage'               => $this->stage ? [...$named($this->stage), 'category' => $this->stage->category, 'stale_after_days' => $this->stage->stale_after_days] : null,
            // The pipeline's stages switched on - and the one it is at - in order: where it stands among them.
            'stages'              => ($this->pipeline?->stages ?? collect())
                ->filter(fn (CrmStage $stage) => $stage->is_active || $stage->id === $this->stage_id)
                ->map(fn (CrmStage $stage) => ['id' => $stage->id, 'name' => $stage->name, 'category' => $stage->category, 'probability' => $stage->probability, 'required_fields' => $stage->needs()])
                ->values(),
            'probability'         => $this->probability,
            'expected_value'      => $this->expected_value,
            'currency'            => $this->currency,
            'weighted_value'      => $this->weighted_value,
            'base_weighted_value' => $this->base_weighted_value,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'overdue'             => (bool) $this->overdue,
            // Phase D3a: its full days at its stage, and whether that is more than the stage's stale-after period.
            'days_at_stage'       => (int) $this->days_at_stage,
            'stale'               => (bool) $this->stale,
            'owner'               => $named($this->owner),
            'team'                => $named($this->team),
            'location'            => $named($this->location),
            'source'              => $named($this->source),
            'priority'            => $this->priority,
            'requirements'        => $this->requirements,
            'next_action'         => $this->next_action,
            'competitors'         => $this->competitors ?? [],
            'tags'                => $this->tags ?? [],
            'notes'               => $this->notes,
            'interested_items'    => $this->interestedItems->map(fn ($item) => [
                'item_id'  => $item->item_id,
                'name'     => $item->name,
                'quantity' => $item->quantity,
            ])->values(),
            // Each stage it has been at, the first first: when, by whom and for how long - the last while it is there.
            'visits'              => $this->stageVisits->map(fn ($visit) => [
                'stage'      => $visit->stage?->name,
                'entered_at' => $visit->entered_at->toIso8601String(),
                'left_at'    => $visit->left_at?->toIso8601String(),
                'by'         => $visit->mover?->name,
                'seconds'    => $visit->seconds(),
            ])->values(),
            'added_by'            => $this->creator?->name,
            'created_at'          => $this->created_at?->toIso8601String(),
            // Phase G3a: its last contact, next activity and days without activity.
            'follow_up'           => $workspace instanceof Workspace ? app(RecordFollowUp::class)->opportunity($workspace, $this->resource, app(WorkspaceAccess::class)->can('crm.activities.view')) : null,
        ];
    }
}
