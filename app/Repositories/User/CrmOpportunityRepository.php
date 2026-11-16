<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmOpportunity;
use App\Models\User\Location;
use App\Models\User\Workspace;

/** A business's opportunities (Doc 17 Phase C1: as converting a lead makes them). */
class CrmOpportunityRepository
{
    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmOpportunity
    {
        return $workspace->crmOpportunities()->create($data);
    }

    /** Whether an opportunity is for $contact: one that has one is deactivated, never deleted. */
    public function usesContact(Contact $contact): bool
    {
        return CrmOpportunity::query()->where('contact_id', $contact->id)->exists();
    }

    /** Whether an opportunity is at $location: one that has one is deactivated, never deleted. */
    public function usesLocation(Location $location): bool
    {
        return CrmOpportunity::query()->where('location_id', $location->id)->exists();
    }

    /**
     * Of $stageIds, those an opportunity is in: a stage that holds one is
     * switched off, never removed.
     *
     * @param  list<int>  $stageIds
     * @return list<int>
     */
    public function stagesInUse(array $stageIds): array
    {
        return $stageIds === [] ? [] : CrmOpportunity::query()->whereIn('stage_id', $stageIds)->distinct()->pluck('stage_id')->map(fn ($id) => (int) $id)->all();
    }
}
