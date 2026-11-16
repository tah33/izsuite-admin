<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;

/**
 * What a lead can do in each status (Doc 17 section 17.7 - Phase B1). An open
 * lead is edited, assigned, unqualified and archived; one with an owner is
 * marked contacted (from Assigned) and qualified (from Assigned or
 * Contacted), and a qualified one converted (Phase C1). An unqualified one
 * is archived or reopened, an archived one reopened. A converted one is
 * final.
 */
class CrmLeadRules
{
    public function actions(CrmLead $lead): array
    {
        return match ($lead->status) {
            CrmLead::NEW         => ['edit', 'assign', 'unqualify', 'archive'],
            CrmLead::ASSIGNED    => ['edit', 'assign', 'contact', 'qualify', 'unqualify', 'archive'],
            CrmLead::CONTACTED   => ['edit', 'assign', 'qualify', 'unqualify', 'archive'],
            CrmLead::QUALIFIED   => ['edit', 'assign', 'convert', 'unqualify', 'archive'],
            CrmLead::UNQUALIFIED => ['archive', 'reopen'],
            CrmLead::ARCHIVED    => ['reopen'],
            default              => [],
        };
    }

    public function allows(CrmLead $lead, string $action): bool
    {
        return in_array($action, $this->actions($lead), true);
    }
}
