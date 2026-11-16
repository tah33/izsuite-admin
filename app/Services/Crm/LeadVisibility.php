<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SalesTeamRepository;
use App\Services\Shared\WorkspaceAccess;

/**
 * Which leads someone sees (Doc 17 section 17.30 - Phase B1): those they own;
 * with View Team Records, also those owned by the people of the sales teams
 * they belong to or manage, and those given to those teams; with View All
 * CRM Records, every one. Whoever may assign leads sees the queue too. All
 * of it only at the locations they may use (Doc 12).
 */
class LeadVisibility
{
    public function __construct(
        protected WorkspaceAccess $access,
        protected SalesTeamRepository $teams,
    ) {}

    /**
     * What CrmLeadRepository narrows a query of $user's leads to.
     *
     * @return array{all: bool, user_id: int, queue: bool, team_ids: list<int>, member_ids: list<int>, location_ids: list<int>|null}
     */
    public function scopeFor(Workspace $workspace, User $user): array
    {
        $all   = $this->access->allows($user, $workspace, 'crm.records.all');
        $teams = ! $all && $this->access->allows($user, $workspace, 'crm.records.team') ? $this->teams->teamsOf($workspace, $user->id) : collect();

        return [
            'all'          => $all,
            'user_id'      => $user->id,
            'queue'        => $this->access->allows($user, $workspace, 'crm.leads.assign'),
            'team_ids'     => $teams->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'member_ids'   => $teams->flatMap(fn ($team) => [$team->manager_id, ...$team->members->pluck('id')])->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'location_ids' => $this->access->locationsOf($user, $workspace),
        ];
    }

    /**
     * Whether $user sees $lead - for anyone, not only the signed-in user: who
     * a comment on it may mention (Phase B2b). Their View Leads is the
     * caller's to check.
     */
    public function sees(Workspace $workspace, User $user, CrmLead $lead): bool
    {
        $scope = $this->scopeFor($workspace, $user);

        if ($scope['location_ids'] !== null && ! in_array($lead->location_id, $scope['location_ids'], true)) {
            return false;
        }

        return $scope['all']
            || $lead->owner_id === $user->id
            || ($scope['queue'] && $lead->owner_id === null)
            || in_array($lead->owner_id, $scope['member_ids'], true)
            || in_array($lead->sales_team_id, $scope['team_ids'], true);
    }
}
