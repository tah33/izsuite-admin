<?php

namespace App\Services\Crm;

use App\Models\User\CrmAssignmentRule;
use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Repositories\User\CrmAssignmentRuleRepository;
use App\Repositories\User\CrmLeadRepository;
use Illuminate\Support\Collection;

/**
 * Who a lead without an owner goes to (Doc 17 section 17.9 - Phase I1): the
 * business's assignment rules switched on, in their order, the first that
 * fits giving it its owner. A rule fits when the lead has all it asks for -
 * its source, location, sales team, country and one of its interested items,
 * those it names - and someone may take it: its person, or one of its sales
 * team's members while the team is on; each an active member who may view
 * leads at the lead's location (LeadAssignments). A team's member is chosen
 * by turns - the one after whom the rule gave a lead to last - or, by
 * workload, the one with the fewest open leads, by turns between equals.
 * None fits: the lead waits in the queue.
 */
class LeadRouting
{
    public function __construct(
        protected CrmAssignmentRuleRepository $rules,
        protected CrmLeadRepository $leads,
        protected LeadAssignments $assignments,
    ) {}

    /**
     * The owner the first fitting rule gives $lead - its interested items
     * $itemIds - and that rule; null for none. Called while the lead is saved:
     * a team's rule is locked, and its turn moved on.
     *
     * @param  list<int>  $itemIds
     * @return array{owner_id: int, rule: CrmAssignmentRule}|null
     */
    public function route(Workspace $workspace, CrmLead $lead, array $itemIds): ?array
    {
        $rules = $this->rules->activeFor($workspace);

        if ($rules->isEmpty()) {
            return null;
        }

        $owners = collect($this->assignments->owners($workspace))->keyBy('id');

        foreach ($rules as $rule) {
            if (! $this->fits($rule, $lead, $itemIds)) {
                continue;
            }

            $people = $this->people($rule, $owners, $lead->location_id);

            if ($people !== []) {
                return ['owner_id' => $rule->user_id ? $people[0] : $this->turn($workspace, $rule, $people), 'rule' => $rule];
            }
        }

        return null;
    }

    /** Whether $lead has all $rule asks for. */
    private function fits(CrmAssignmentRule $rule, CrmLead $lead, array $itemIds): bool
    {
        return ($rule->source_id === null || $rule->source_id === $lead->source_id)
            && ($rule->location_id === null || $rule->location_id === $lead->location_id)
            && ($rule->sales_team_id === null || $rule->sales_team_id === $lead->sales_team_id)
            && ($rule->country === null || $rule->country === $lead->address_country)
            && ($rule->item_id === null || in_array($rule->item_id, $itemIds, true));
    }

    /**
     * Who $rule may give a lead at $locationId to - its person, or its team's
     * members while the team is on - among $owners, by name.
     *
     * @return list<int>
     */
    private function people(CrmAssignmentRule $rule, Collection $owners, ?int $locationId): array
    {
        $ids = $rule->user_id ? [$rule->user_id] : ($rule->team?->is_active ? $rule->team->members->pluck('id')->all() : []);

        return collect($ids)
            ->map(fn ($id) => $owners->get((int) $id))
            ->filter(fn (?array $owner) => $owner !== null && ($locationId === null || $owner['locations'] === null || in_array($locationId, $owner['locations'], true)))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->pluck('id')
            ->values()
            ->all();
    }

    /** Whose turn it is among $people by $rule's method, its turn moved on to them. */
    private function turn(Workspace $workspace, CrmAssignmentRule $rule, array $people): int
    {
        $last  = $this->rules->lock($rule)->last_user_id;
        $at    = array_search($last, $people, true);
        // In turn: those after the last one given a lead, then those before.
        $turns = $at === false ? $people : [...array_slice($people, $at + 1), ...array_slice($people, 0, $at + 1)];

        if ($rule->method === CrmAssignmentRule::WORKLOAD) {
            $open  = $this->leads->openCountsByOwner($workspace, $people);
            $turns = collect($turns)->sortBy(fn (int $id, int $index) => [$open[$id] ?? 0, $index])->values()->all();
        }

        $this->rules->tookTurn($rule, $turns[0]);

        return $turns[0];
    }
}
