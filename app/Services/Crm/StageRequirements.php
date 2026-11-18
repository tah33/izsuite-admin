<?php

namespace App\Services\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\CrmStage;
use Illuminate\Validation\ValidationException;

/**
 * The opportunity's details a stage needs filled in before one enters it
 * (Doc 17 section 17.13 - Phase D3a): checked when it is added at the stage,
 * moved, reopened or converted there, and - a won or lost stage - when it is
 * won or lost. Emptying one later never moves it back.
 */
class StageRequirements
{
    /**
     * The details of $stage's needs that $values leaves empty, by their
     * words. $values holds the requirable details (see CrmStage::REQUIRABLE):
     * texts, ids, the competitors and interested items as lists.
     *
     * @return list<string>
     */
    public function missing(CrmStage $stage, array $values): array
    {
        $missing = [];

        foreach ($stage->needs() as $field) {
            $value = $values[$field] ?? null;

            if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                $missing[] = CrmStage::REQUIRABLE[$field];
            }
        }

        return $missing;
    }

    /** Refuses $values short of what $stage needs, on $field, naming the details missing. */
    public function ensure(CrmStage $stage, array $values, string $field = 'stage_id'): void
    {
        $missing = $this->missing($stage, $values);

        if ($missing !== []) {
            throw ValidationException::withMessages([$field => "{$stage->name} needs ".$this->listed($missing).' filled in first.']);
        }
    }

    /** $opportunity's requirable details as it is now, its interested items loaded. */
    public function valuesOf(CrmOpportunity $opportunity): array
    {
        return [
            ...$opportunity->only(['contact_person', 'sales_team_id', 'source_id', 'priority', 'next_action', 'requirements']),
            'competitors'      => $opportunity->competitors ?? [],
            'interested_items' => $opportunity->interestedItems->all(),
        ];
    }

    /** "Next Action", "Next Action and Priority", "Sales Team, Next Action and Priority". */
    private function listed(array $words): string
    {
        $last = array_pop($words);

        return $words === [] ? $last : implode(', ', $words)." and {$last}";
    }
}
