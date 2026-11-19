<?php

namespace App\Repositories\User;

use App\Models\User\CrmScoringRule;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** A business's lead scoring rules (Doc 17 Phase I2), in the order they were added. */
class CrmScoringRuleRepository
{
    /** What a rule's row names: the source, location or item it asks for. */
    private const NAMED = ['source:id,name', 'location:id,name', 'item:id,name'];

    /** Its rules, with what each names. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->crmScoringRules()->with(self::NAMED)->orderBy('id')->get();
    }

    /** Its rules switched on: what LeadScoring adds up. */
    public function activeFor(Workspace $workspace): Collection
    {
        return $workspace->crmScoringRules()->where('is_active', true)->orderBy('id')->get();
    }

    /** Whether it scores its leads: a rule is switched on. */
    public function anyActive(Workspace $workspace): bool
    {
        return $workspace->crmScoringRules()->where('is_active', true)->exists();
    }

    /**
     * The businesses with a scoring rule switched on: those the hourly
     * scoring visits.
     *
     * @return list<int>
     */
    public function scoringWorkspaceIds(): array
    {
        return CrmScoringRule::query()->where('is_active', true)->distinct()->orderBy('workspace_id')->pluck('workspace_id')->map(fn ($id) => (int) $id)->all();
    }

    public function findFor(Workspace $workspace, int $id): ?CrmScoringRule
    {
        return $workspace->crmScoringRules()->with(self::NAMED)->find($id);
    }

    /** Whether another of its rules - $exceptId aside - is called $name. */
    public function nameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return $workspace->crmScoringRules()->where('name', $name)->when($exceptId, fn ($query, $id) => $query->whereKeyNot($id))->exists();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmScoringRule
    {
        return $workspace->crmScoringRules()->create($data);
    }

    public function update(CrmScoringRule $rule, array $data): CrmScoringRule
    {
        $rule->update($data);

        return $rule;
    }

    public function delete(CrmScoringRule $rule): void
    {
        $rule->delete();
    }
}
