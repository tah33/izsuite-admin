<?php

namespace App\Services\Crm;

use App\Models\User\CrmActivity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Shared\WorkspaceAccess;

/**
 * Which activities a user sees (Doc 17 section 17.17 - Phase E1).
 *
 * The Activities page shows their own - those given to them - and, with View
 * Team Records, their teams' members', or with View All CRM Records every
 * one: at the locations they may use, where an activity's location is its
 * record's (a contact's has none, so it is no location's to hide).
 *
 * On a record's page, its activities are seen by whoever sees the record:
 * its opportunity's or lead's visibility, or View Contacts for a contact.
 */
class ActivityVisibility
{
    public function __construct(
        protected LeadVisibility $leads,
        protected OpportunityVisibility $opportunities,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * What CrmActivityRepository narrows the Activities page to.
     *
     * @return array{all: bool, user_id: int, member_ids: list<int>, location_ids: list<int>|null}
     */
    public function scopeFor(Workspace $workspace, User $user): array
    {
        $scope = $this->leads->scopeFor($workspace, $user);

        return ['all' => $scope['all'], 'user_id' => $user->id, 'member_ids' => $scope['member_ids'], 'location_ids' => $scope['location_ids']];
    }

    /** Whether $user sees $activity: on the Activities page's terms, or as one of a record they see. */
    public function sees(Workspace $workspace, User $user, CrmActivity $activity): bool
    {
        $scope = $this->scopeFor($workspace, $user);
        $here  = $scope['location_ids'] === null || $activity->location_id === null || in_array($activity->location_id, $scope['location_ids'], true);

        if ($here && ($scope['all'] || $activity->assigned_to === $user->id || in_array($activity->assigned_to, $scope['member_ids'], true))) {
            return true;
        }

        return $this->seesRecord($workspace, $user, $activity);
    }

    /** Whether $user sees the record $activity is for - its opportunity, once a lead's went into one. */
    public function seesRecord(Workspace $workspace, User $user, CrmActivity $activity): bool
    {
        if ($activity->opportunity_id) {
            return $this->access->allows($user, $workspace, 'crm.opportunities.view') && $this->opportunities->sees($workspace, $user, $activity->opportunity);
        }

        if ($activity->lead_id) {
            return $this->access->allows($user, $workspace, 'crm.leads.view') && $this->leads->sees($workspace, $user, $activity->lead);
        }

        return $this->access->allows($user, $workspace, 'contacts.view');
    }
}
