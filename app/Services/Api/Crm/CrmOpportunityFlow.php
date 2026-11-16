<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmOpportunityRules;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving an opportunity between stages (Doc 17 section 17.13 - Phase D1),
 * with Change Stages: to another open stage of its pipeline switched on. The
 * stage it leaves keeps the time it spent there, and the move is logged. Its
 * probability becomes the new stage's, unless someone who may change
 * probabilities gives another. Winning and losing are Phase D2's.
 */
class CrmOpportunityFlow
{
    use RefusesConflicts;

    public function __construct(
        protected CrmOpportunityService $service,
        protected CrmOpportunityRepository $opportunities,
        protected CrmOpportunityRules $rules,
    ) {}

    /** $data is validated (see CrmOpportunityStageRequest): stage_id, and a probability or none. */
    public function changeStage(Workspace $workspace, User $user, int $id, array $data): CrmOpportunity
    {
        $opportunity = $this->service->find($workspace, $user, $id);

        if (! $this->rules->allows($opportunity, 'stage')) {
            $this->refuse('A won or lost opportunity is reopened before it moves.');
        }

        $stage = $this->service->openStages($opportunity->pipeline)->firstWhere('id', (int) $data['stage_id'])
            ?? throw ValidationException::withMessages(['stage_id' => "Choose one of the pipeline's open stages."]);
        $from  = $opportunity->stage;

        if ($stage->id === $opportunity->stage_id) {
            throw ValidationException::withMessages(['stage_id' => "It is at {$stage->name} already."]);
        }

        $probability = $this->service->probability($data, $stage->probability);

        DB::transaction(function () use ($opportunity, $user, $stage, $probability) {
            // Moved by someone else meanwhile: theirs stands, and this one says so.
            if ($this->opportunities->lock($opportunity)->stage_id !== $opportunity->stage_id) {
                $this->refuse('Someone moved this opportunity meanwhile. Open it again to see where it is.');
            }

            $this->opportunities->enterStage($opportunity, $stage->id, $user->id);
            $this->opportunities->update($opportunity, ['stage_id' => $stage->id, 'probability' => $probability, 'stage_entered_at' => now()]);
        });

        ActivityLogService::record('updated', 'Moved '.$this->service->describe($opportunity)." from {$from?->name} to {$stage->name}", $opportunity, [
            'source'      => 'api',
            'from_stage'  => $from?->id,
            'to_stage'    => $stage->id,
            'probability' => $probability,
        ]);

        return $this->service->shown($workspace, $this->opportunities->loadShown($opportunity));
    }
}
