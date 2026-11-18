<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmOpportunityChanges;
use App\Services\Crm\CrmOpportunityRules;
use App\Services\Crm\PipelineChoices;
use App\Services\Crm\StageRequirements;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving an opportunity between stages (Doc 17 section 17.13 - Phase D1),
 * with Change Stages: to another open stage of its pipeline switched on -
 * or (Phase D3b1) of another pipeline switched on. The
 * stage it leaves keeps the time it spent there, and the move is logged. Its
 * probability becomes the new stage's, unless someone who may change
 * probabilities gives another. Winning and losing are CrmOpportunityClosing's.
 *
 * And archiving one, in any status (Phase D2a), with Create and Edit: it
 * leaves the lists and no longer changes until it is restored.
 */
class CrmOpportunityFlow
{
    use RefusesConflicts;

    /** Whether the last changeStage() took it into another pipeline (Phase D3b1), for its message. */
    public bool $changedPipeline = false;

    public function __construct(
        protected CrmOpportunityService $service,
        protected CrmOpportunityRepository $opportunities,
        protected CrmOpportunityRules $rules,
        protected StageRequirements $requirements,
        protected PipelineChoices $choices,
    ) {}

    public function archive(Workspace $workspace, User $user, int $id): CrmOpportunity
    {
        return $this->setArchived($workspace, $user, $id, true);
    }

    public function restore(Workspace $workspace, User $user, int $id): CrmOpportunity
    {
        return $this->setArchived($workspace, $user, $id, false);
    }

    /**
     * $data is validated (see CrmOpportunityStageRequest): stage_id, and a
     * probability or none. The stage is an open one of its pipeline - or
     * (Phase D3b1) of another pipeline switched on, which it then moves to.
     */
    public function changeStage(Workspace $workspace, User $user, int $id, array $data): CrmOpportunity
    {
        $opportunity = $this->service->find($workspace, $user, $id);

        if (! $this->rules->allows($opportunity, 'stage')) {
            $this->refuse($opportunity->isArchived() ? 'An archived opportunity is restored before it moves.' : 'A won or lost opportunity is reopened before it moves.');
        }

        // Its own pipeline's stages stay open to it, even with the pipeline switched off.
        $stage    = $this->choices->stage($this->choices->enterable($workspace, $opportunity->pipeline), (int) $data['stage_id']);
        $from     = $opportunity->stage;
        $pipeline = $stage->pipeline;
        $leaving  = $opportunity->pipeline;
        $moving   = $pipeline->id !== $opportunity->pipeline_id;

        if ($stage->id === $opportunity->stage_id) {
            throw ValidationException::withMessages(['stage_id' => "It is at {$stage->name} already."]);
        }

        // What the stage needs filled in before it enters (Phase D3a).
        $this->requirements->ensure($stage, $this->requirements->valuesOf($opportunity));

        $probability = $this->service->probability($data, $stage->probability);
        $before      = $opportunity->probability;

        DB::transaction(function () use ($opportunity, $user, $stage, $pipeline, $probability) {
            // Moved by someone else meanwhile: theirs stands, and this one says so.
            if ($this->opportunities->lock($opportunity)->stage_id !== $opportunity->stage_id) {
                $this->refuse('Someone moved this opportunity meanwhile. Open it again to see where it is.');
            }

            $this->opportunities->enterStage($opportunity, $stage->id, $user->id);
            $this->opportunities->update($opportunity, ['pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'probability' => $probability, 'stage_entered_at' => now()]);
        });

        // Its timeline names the stages, not their ids (Phase D2b) - each with its pipeline when it changes them.
        $moved = $moving ? "from {$from?->name} ({$leaving?->name}) to {$stage->name} ({$pipeline->name})" : "from {$from?->name} to {$stage->name}";

        $this->changedPipeline = $moving;

        ActivityLogService::record('updated', 'Moved '.$this->service->describe($opportunity)." {$moved}", $opportunity, [
            'source'      => 'api',
            'from_stage'  => $from?->id,
            'to_stage'    => $stage->id,
            'probability' => $probability,
            'changes'     => CrmOpportunityChanges::of([
                ...($moving ? ['pipeline' => [$leaving?->name, $pipeline->name]] : []),
                'stage'       => [$from?->name, $stage->name],
                'probability' => [$before, $probability],
            ]),
        ]);

        return $this->service->shown($workspace, $this->opportunities->loadShown($opportunity));
    }

    private function setArchived(Workspace $workspace, User $user, int $id, bool $archived): CrmOpportunity
    {
        $opportunity = $this->service->find($workspace, $user, $id);

        if (! $this->rules->allows($opportunity, $archived ? 'archive' : 'restore')) {
            $this->refuse($archived ? 'This opportunity is archived already.' : 'This opportunity is not archived.');
        }

        $this->opportunities->update($opportunity, ['archived_at' => $archived ? now() : null, 'archived_by' => $archived ? $user->id : null]);

        // Its words say it all: no columns of who and when in its timeline.
        ActivityLogService::record('updated', ($archived ? 'Archived ' : 'Restored ').$this->service->describe($opportunity), $opportunity, [
            'source' => 'api', 'archived' => $archived, 'changes' => [],
        ]);

        return $this->service->shown($workspace, $this->opportunities->loadShown($opportunity));
    }
}
