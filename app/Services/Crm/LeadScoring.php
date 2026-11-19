<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmScoringRule;
use App\Models\User\Workspace;
use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\CrmEmailRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmScoringRuleRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Shared\BusinessCalendar;
use App\Services\User\Apps\WorkspaceApps;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lead scoring (Doc 17 section 17.22 - Phase I2). An open lead's score is the
 * points of the business's scoring rules switched on that it fits, added up
 * and kept from 0 to 100, with those rules' names and points - its factors -
 * so anyone who sees the lead sees what made its score. A closed lead
 * (converted, unqualified or archived) keeps the score it had; with no rule
 * switched on, no lead has one. A score never qualifies, unqualifies or
 * archives a lead: people do that.
 *
 * A lead is scored again when it is added or changed, marked contacted or
 * reopened, and when an activity is done or an e-mail sent for it; every
 * hour (crm:score-leads), as how long it has gone without activity and
 * whether it was contacted in time change by themselves; and a change to the
 * rules scores every open lead again (ScoreLeads). Only a score that changed
 * is written.
 */
class LeadScoring
{
    public function __construct(
        protected CrmScoringRuleRepository $rules,
        protected CrmLeadRepository $leads,
        protected CrmActivityRepository $activities,
        protected CrmEmailRepository $emails,
        protected WorkspaceRepository $workspaces,
        protected FollowUpCutoffs $cutoffs,
        protected ExchangeRateService $rates,
        protected BusinessCalendar $calendar,
    ) {}

    /** Lead $leadId scored again, while it is open. */
    public function rescore(Workspace $workspace, int $leadId): void
    {
        $leads = $this->leads->forScoring($workspace, [$leadId])->filter(fn (CrmLead $lead) => $lead->isOpen());
        $rates = [];

        if ($leads->isNotEmpty()) {
            $this->score($workspace, $this->rules->activeFor($workspace), $leads, $rates);
        }
    }

    /**
     * Every open lead of $workspace scored again - or, with no rule switched
     * on, every lead's score gone: how many scores changed.
     */
    public function rescoreAll(Workspace $workspace): int
    {
        $rules = $this->rules->activeFor($workspace);

        if ($rules->isEmpty()) {
            return $this->leads->unscoreAll($workspace);
        }

        $changed = 0;
        $rates   = [];

        $this->leads->eachOpenForScoring($workspace, function (Collection $leads) use ($workspace, $rules, &$changed, &$rates) {
            $changed += $this->score($workspace, $rules, $leads, $rates);
        });

        return $changed;
    }

    /** rescoreAll() for business $workspaceId, when it is still there: the rules' changes (ScoreLeads). */
    public function rescoreBusiness(int $workspaceId): int
    {
        $workspace = $this->workspaces->find($workspaceId);

        return $workspace ? $this->rescoreAll($workspace) : 0;
    }

    /** rescoreAll() for each business with a rule switched on and CRM in use: the hourly run. */
    public function rescoreEveryBusiness(): int
    {
        $changed = 0;

        foreach ($this->rules->scoringWorkspaceIds() as $id) {
            $workspace = $this->workspaces->find($id);

            if ($workspace && WorkspaceApps::for($workspace)->has('crm')) {
                $changed += $this->rescoreAll($workspace);
            }
        }

        return $changed;
    }

    /**
     * $leads, open ones, scored by $rules and written where their score or
     * factors changed: how many. $rates keeps each currency's rate today.
     *
     * @param  Collection<int, CrmScoringRule>  $rules
     * @param  Collection<int, CrmLead>  $leads
     */
    private function score(Workspace $workspace, Collection $rules, Collection $leads, array &$rates): int
    {
        $factors   = $rules->pluck('factor')->unique();
        $ids       = $leads->modelKeys();
        $interests = $factors->contains('item') || $rules->contains('option', 'interested_items') ? $this->leads->interestsOf($ids) : [];
        $activity  = $factors->intersect(['engagement', 'quiet'])->isNotEmpty();
        $done      = $activity ? $this->activities->doneByLead($workspace, $ids) : [];
        $sent      = $activity ? $this->emails->sentByLead($workspace, $ids) : [];
        $lateFrom  = $this->cutoffs->uncontactedBefore($workspace);
        $today     = $this->calendar->today($workspace)->toDateString();
        $changed   = 0;

        foreach ($leads as $lead) {
            $facts = [
                'items'      => $interests[$lead->id] ?? null,
                'value'      => $this->inBase($workspace, $lead->estimated_value, $lead->currency, $today, $rates),
                'budget'     => $this->inBase($workspace, $lead->budget, $lead->currency, $today, $rates),
                'engagement' => ($done[$lead->id]['count'] ?? 0) + ($sent[$lead->id]['count'] ?? 0),
                'quiet_days' => $this->daysWithoutActivity($lead, $done[$lead->id]['last_at'] ?? null, $sent[$lead->id]['last_at'] ?? null),
                'late'       => $lead->first_contacted_at === null && $lead->created_at->lt($lateFrom),
            ];

            $fitting = $rules->filter(fn (CrmScoringRule $rule) => $this->fits($rule, $lead, $facts));
            $score   = $rules->isEmpty() ? null : max(0, min(100, (int) $fitting->sum('points')));
            $made    = $rules->isEmpty() ? null : $fitting->sortByDesc('points')->map(fn (CrmScoringRule $rule) => ['name' => $rule->name, 'points' => $rule->points])->values()->all();

            if ($score !== $lead->score || $made !== $lead->score_factors) {
                $this->leads->scored($lead->id, $score, $made);
                $changed++;
            }
        }

        return $changed;
    }

    /** Whether $lead has what $rule's factor asks for. */
    private function fits(CrmScoringRule $rule, CrmLead $lead, array $facts): bool
    {
        return match ($rule->factor) {
            'source'          => $rule->source_id !== null && $lead->source_id === $rule->source_id,
            'location'        => $rule->location_id !== null && $lead->location_id === $rule->location_id,
            'detail'          => $this->hasDetail($lead, (string) $rule->option, $facts['items'] !== null),
            'item'            => $rule->item_id !== null && in_array($rule->item_id, $facts['items'] ?? [], true),
            'value'           => $this->atLeast($facts['value'], $rule->threshold),
            'budget'          => $this->atLeast($facts['budget'], $rule->threshold),
            'purchase_period' => $lead->purchase_period !== null && $lead->purchase_period === $rule->option,
            'business_size'   => filled($lead->business_size) && filled($rule->option) && Str::contains($lead->business_size, $rule->option, true),
            'engagement'      => $facts['engagement'] >= (int) $rule->threshold,
            'response'        => $rule->option === 'contacted' ? $lead->first_contacted_at !== null : ($rule->option === 'late' && $facts['late']),
            'quiet'           => $facts['quiet_days'] >= (int) $rule->threshold,
            default           => false,
        };
    }

    /** Whether $lead has $detail (CrmScoringRule::DETAILS) filled in. */
    private function hasDetail(CrmLead $lead, string $detail, bool $hasItems): bool
    {
        return match ($detail) {
            'address'          => filled($lead->address_line1) || filled($lead->address_city),
            'interested_items' => $hasItems,
            'email', 'phone', 'business_name', 'job_title', 'decision_maker', 'customer_need' => filled($lead->{$detail}),
            default            => false,
        };
    }

    /** $amount in $currency, in the base currency at today's rate - null with none, or no rate today. */
    private function inBase(Workspace $workspace, ?string $amount, ?string $currency, string $today, array &$rates): ?string
    {
        if ($amount === null) {
            return null;
        }

        $currency ??= $workspace->base_currency;
        $rate       = $rates[$currency] ??= $this->rates->rateOn($workspace, $currency, $today) ?? '';

        return $rate === '' ? null : bcmul($amount, $rate, 2);
    }

    private function atLeast(?string $amount, ?string $threshold): bool
    {
        return $amount !== null && $threshold !== null && bccomp($amount, $threshold, 2) >= 0;
    }

    /**
     * Its full days without activity, as its page counts them (RecordFollowUp):
     * since its last contact - an activity done, an e-mail sent, its marking
     * as contacted - or since it was added.
     */
    private function daysWithoutActivity(CrmLead $lead, ?string $lastDone, ?string $lastSent): int
    {
        $since = collect([$lastDone ? Carbon::parse($lastDone) : null, $lastSent ? Carbon::parse($lastSent) : null, $lead->contacted_at])
            ->filter()
            ->sortByDesc(fn (CarbonInterface $at) => $at->getTimestamp())
            ->first() ?? $lead->created_at;

        return (int) floor(max(0, $since->diffInSeconds(now())) / 86400);
    }
}
