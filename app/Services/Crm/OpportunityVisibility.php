<?php

namespace App\Services\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;

/**
 * Which opportunities someone sees (Doc 17 section 17.30 - Phase D1): as
 * with leads (see LeadVisibility) - their own; with View Team Records, those
 * of the people of their sales teams and those given to the teams; with View
 * All CRM Records, every one; only at the locations they may use - but with
 * no queue: an opportunity always has an owner.
 */
class OpportunityVisibility
{
    public function __construct(
        protected LeadVisibility $leads,
    ) {}

    /**
     * What CrmOpportunityRepository narrows a query of $user's opportunities to.
     *
     * @return array{all: bool, user_id: int, queue: bool, team_ids: list<int>, member_ids: list<int>, location_ids: list<int>|null}
     */
    public function scopeFor(Workspace $workspace, User $user): array
    {
        return [...$this->leads->scopeFor($workspace, $user), 'queue' => false];
    }

    /**
     * Whether $user sees $opportunity - for anyone, not only the signed-in
     * user: who a comment on it may mention, and whose its files are (Phase
     * D2b). Their View Opportunities is the caller's to check.
     */
    public function sees(Workspace $workspace, User $user, CrmOpportunity $opportunity): bool
    {
        $scope = $this->scopeFor($workspace, $user);

        if ($scope['location_ids'] !== null && ! in_array($opportunity->location_id, $scope['location_ids'], true)) {
            return false;
        }

        return $scope['all']
            || $opportunity->owner_id === $user->id
            || in_array($opportunity->owner_id, $scope['member_ids'], true)
            || in_array($opportunity->sales_team_id, $scope['team_ids'], true);
    }
}
