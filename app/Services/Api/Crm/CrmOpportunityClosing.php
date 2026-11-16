<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\CrmStage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmOpportunityRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * How an opportunity ends (Doc 17 section 17.16 - Phase D2a), with Mark Won
 * or Lost, and reopening one, with Reopen Won or Lost.
 *
 * - Won: on a day no later than today, at its final value - which becomes
 *   its value - with a reason or none. It goes to the pipeline's won stage
 *   at 100%. It is not a sale: sales come from invoices.
 * - Lost: on a day no later than today, for one of Settings → CRM's lost
 *   reasons, with the competitor, notes and a possible follow-up day. It
 *   goes to the lost stage at 0%.
 * - Reopened: back to an open stage - the last one it was at, unless
 *   another is chosen - at that stage's probability, its ending cleared.
 *
 * Each goes into its stage history and the Activity Log.
 */
class CrmOpportunityClosing
{
    use RefusesConflicts;

    public function __construct(
        protected CrmOpportunityService $service,
        protected CrmOpportunityRepository $opportunities,
        protected CrmOptionRepository $options,
        protected CrmOpportunityRules $rules,
        protected BusinessCalendar $calendar,
    ) {}

    /** $data is validated (see CrmOpportunityWinRequest): closed_on, value, reason. */
    public function win(Workspace $workspace, User $user, int $id, array $data): CrmOpportunity
    {
        $opportunity = $this->allowed($workspace, $user, $id, 'win', 'Only an open opportunity is won.');
        $stage       = $this->closingStage($opportunity, CrmStage::WON);

        $this->ensureNotLater($workspace, $data['closed_on']);

        $this->move($opportunity, $user, $stage, [
            'status'         => CrmOpportunity::WON,
            'probability'    => 100,
            'expected_value' => $data['value'] ?? $opportunity->expected_value,
            'closed_on'      => $data['closed_on'],
            'closed_by'      => $user->id,
            'won_reason'     => $data['reason'] ?? null,
        ]);

        ActivityLogService::record('updated', 'Marked '.$this->service->describe($opportunity).' won', $opportunity, [
            'source' => 'api', 'status' => CrmOpportunity::WON, 'value' => (string) $opportunity->expected_value, 'closed_on' => $data['closed_on'],
        ]);

        return $this->shown($workspace, $opportunity);
    }

    /** $data is validated (see CrmOpportunityLoseRequest): closed_on, lost_reason_id, competitor, notes, follow_up_on. */
    public function lose(Workspace $workspace, User $user, int $id, array $data): CrmOpportunity
    {
        $opportunity = $this->allowed($workspace, $user, $id, 'lose', 'Only an open opportunity is lost.');
        $stage       = $this->closingStage($opportunity, CrmStage::LOST);
        $reason      = $this->options->findFor($workspace, (int) $data['lost_reason_id']);

        if (! $reason || $reason->kind !== 'lost_reason' || ! $reason->is_active) {
            throw ValidationException::withMessages(['lost_reason_id' => 'Choose one of the lost reasons in Settings → CRM.']);
        }

        $this->ensureNotLater($workspace, $data['closed_on']);

        $this->move($opportunity, $user, $stage, [
            'status'          => CrmOpportunity::LOST,
            'probability'     => 0,
            'closed_on'       => $data['closed_on'],
            'closed_by'       => $user->id,
            'lost_reason_id'  => $reason->id,
            'lost_competitor' => $data['competitor'] ?? null,
            'lost_notes'      => $data['notes'] ?? null,
            'follow_up_on'    => $data['follow_up_on'] ?? null,
        ]);

        ActivityLogService::record('updated', 'Marked '.$this->service->describe($opportunity).' lost: '.Str::limit($reason->name, 80), $opportunity, [
            'source' => 'api', 'status' => CrmOpportunity::LOST, 'lost_reason_id' => $reason->id, 'closed_on' => $data['closed_on'],
        ]);

        return $this->shown($workspace, $opportunity);
    }

    /** $data is validated (see CrmOpportunityReopenRequest): the open stage it goes back to, or none for the last one it was at. */
    public function reopen(Workspace $workspace, User $user, int $id, array $data): CrmOpportunity
    {
        $opportunity = $this->allowed($workspace, $user, $id, 'reopen', 'Only a won or lost opportunity is reopened.');
        $open        = $this->service->openStages($opportunity->pipeline);
        $stage       = isset($data['stage_id'])
            ? $open->firstWhere('id', (int) $data['stage_id'])
            : $open->firstWhere('id', $this->lastOpenStageId($opportunity)) ?? $open->first();

        if (! $stage) {
            throw ValidationException::withMessages(['stage_id' => "Choose one of the pipeline's open stages."]);
        }

        $this->move($opportunity, $user, $stage, [
            'status'      => CrmOpportunity::OPEN,
            'probability' => $stage->probability,
            ...array_fill_keys(CrmOpportunity::OUTCOME_FIELDS, null),
        ]);

        ActivityLogService::record('updated', 'Reopened '.$this->service->describe($opportunity)." at {$stage->name}", $opportunity, [
            'source' => 'api', 'status' => CrmOpportunity::OPEN, 'to_stage' => $stage->id,
        ]);

        return $this->shown($workspace, $opportunity);
    }

    /** $id - one $user sees - while it may take $action. */
    private function allowed(Workspace $workspace, User $user, int $id, string $action, string $refusal): CrmOpportunity
    {
        $opportunity = $this->service->find($workspace, $user, $id);

        if (! $this->rules->allows($opportunity, $action)) {
            $this->refuse($opportunity->isArchived() ? 'An archived opportunity is restored before it changes.' : $refusal);
        }

        return $opportunity;
    }

    /** Its pipeline's first $category stage switched on: where a won or lost one goes. */
    private function closingStage(CrmOpportunity $opportunity, string $category): CrmStage
    {
        return $opportunity->pipeline->stages->first(fn (CrmStage $stage) => $stage->is_active && $stage->category === $category)
            ?? throw ValidationException::withMessages(['stage_id' => "The pipeline has no {$category} stage switched on: add one in Settings → CRM."]);
    }

    /** A won or lost day is today or before - the business's today. */
    private function ensureNotLater(Workspace $workspace, string $day): void
    {
        if ($day > $this->calendar->today($workspace)->toDateString()) {
            throw ValidationException::withMessages(['closed_on' => 'Give today or a day before.']);
        }
    }

    /** The open stage it was last at before it ended: where reopening takes it. */
    private function lastOpenStageId(CrmOpportunity $opportunity): ?int
    {
        $open = $opportunity->pipeline->stages->where('category', CrmStage::OPEN)->pluck('id')->all();

        return $opportunity->stageVisits->reverse()->first(fn ($visit) => in_array($visit->crm_stage_id, $open, true))?->crm_stage_id;
    }

    /** To $stage with $fields, under a row lock: changed by someone else meanwhile, it is left as they made it. */
    private function move(CrmOpportunity $opportunity, User $user, CrmStage $stage, array $fields): void
    {
        DB::transaction(function () use ($opportunity, $user, $stage, $fields) {
            $now = $this->opportunities->lock($opportunity);

            if ($now->status !== $opportunity->status || $now->stage_id !== $opportunity->stage_id || $now->archived_at !== null) {
                $this->refuse('Someone changed this opportunity meanwhile. Open it again to see where it is.');
            }

            $this->opportunities->enterStage($opportunity, $stage->id, $user->id);
            $this->opportunities->update($opportunity, [...$fields, 'stage_id' => $stage->id, 'stage_entered_at' => now()]);
        });
    }

    private function shown(Workspace $workspace, CrmOpportunity $opportunity): CrmOpportunity
    {
        return $this->service->shown($workspace, $this->opportunities->loadShown($opportunity));
    }
}
