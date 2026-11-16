<?php

namespace App\Services\Crm;

use App\Models\User\CrmOpportunity;

/**
 * What an opportunity can do in each status (Doc 17 section 17.15 - Phases
 * D1 and D2a): an open one is edited, moved to another open stage, won, lost
 * or archived; a won or lost one is reopened or archived; an archived one -
 * whatever its status - only restored.
 */
class CrmOpportunityRules
{
    public function actions(CrmOpportunity $opportunity): array
    {
        if ($opportunity->isArchived()) {
            return ['restore'];
        }

        return match ($opportunity->status) {
            CrmOpportunity::OPEN                       => ['edit', 'stage', 'win', 'lose', 'archive'],
            CrmOpportunity::WON, CrmOpportunity::LOST => ['reopen', 'archive'],
            default                                    => [],
        };
    }

    public function allows(CrmOpportunity $opportunity, string $action): bool
    {
        return in_array($action, $this->actions($opportunity), true);
    }
}
