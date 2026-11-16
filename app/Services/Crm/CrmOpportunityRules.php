<?php

namespace App\Services\Crm;

use App\Models\User\CrmOpportunity;

/**
 * What an opportunity can do in each status (Doc 17 section 17.15 - Phase
 * D1): an open one is edited and moved to another open stage. Winning,
 * losing, reopening and archiving come with Phase D2.
 */
class CrmOpportunityRules
{
    public function actions(CrmOpportunity $opportunity): array
    {
        return match ($opportunity->status) {
            CrmOpportunity::OPEN => ['edit', 'stage'],
            default              => [],
        };
    }

    public function allows(CrmOpportunity $opportunity, string $action): bool
    {
        return in_array($action, $this->actions($opportunity), true);
    }
}
