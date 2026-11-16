<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;
use App\Models\User\Workspace;
use App\Repositories\User\CrmSettingRepository;

/**
 * What a lead still needs before it is qualified (Doc 17 section 17.10 -
 * Phase B2a): the details Settings > CRM requires that it has not filled in.
 * Qualify refuses until there are none, and a lead's page lists them.
 */
class LeadQualification
{
    public function __construct(
        protected CrmSettingRepository $settings,
    ) {}

    /**
     * Keys of CrmLead::QUALIFYING, in that order.
     *
     * @return list<string>
     */
    public function missing(Workspace $workspace, CrmLead $lead): array
    {
        return array_values(array_filter(
            $this->settings->forWorkspace($workspace)->qualificationRequired(),
            fn (string $key) => $key === 'interested_items' ? $lead->interestedItems->isEmpty() : trim((string) $lead->{$key}) === '',
        ));
    }

    /** $keys as a refusal says them: "the customer need, the budget and the next action". */
    public function describe(array $keys): string
    {
        $named = array_map(fn (string $key) => CrmLead::QUALIFYING[$key], $keys);
        $last  = array_pop($named);

        return ($named ? implode(', ', $named).' and ' : '').$last;
    }
}
