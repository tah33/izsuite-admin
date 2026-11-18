<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings → CRM's lead assignment rules (Doc 17 Phase I1), as
 * CrmAssignmentRuleService::index() gives them: the rules in order, and whom
 * a rule can give leads to - the people who may own leads, the sales teams
 * switched on.
 */
class CrmAssignmentRulesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rules'  => CrmAssignmentRuleResource::collection($this->resource['rules']),
            'owners' => $this->resource['owners'],
            'teams'  => $this->resource['teams']->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])->values(),
        ];
    }
}
