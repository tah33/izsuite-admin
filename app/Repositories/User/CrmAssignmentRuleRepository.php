<?php

namespace App\Repositories\User;

use App\Models\User\CrmAssignmentRule;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** A business's lead assignment rules (Doc 17 Phase I1), in their order. */
class CrmAssignmentRuleRepository
{
    /** What a rule's row names: what it asks for and whom it gives leads to. */
    private const NAMED = ['source:id,name', 'location:id,name', 'salesTeam:id,name', 'item:id,name', 'user:id,first_name,last_name', 'team:id,name'];

    /** Its rules in order, with what each names. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->crmAssignmentRules()->with(self::NAMED)->orderBy('position')->orderBy('id')->get();
    }

    /** Its rules switched on, in order, each with its sales team's members: what LeadRouting tries. */
    public function activeFor(Workspace $workspace): Collection
    {
        return $workspace->crmAssignmentRules()->where('is_active', true)->with(['team:id,name,is_active', 'team.members:id'])->orderBy('position')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?CrmAssignmentRule
    {
        return $workspace->crmAssignmentRules()->with(self::NAMED)->find($id);
    }

    /** Whether another of its rules - $exceptId aside - is called $name. */
    public function nameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return $workspace->crmAssignmentRules()->where('name', $name)->when($exceptId, fn ($query, $id) => $query->whereKeyNot($id))->exists();
    }

    /** The place after its last rule. */
    public function nextPosition(Workspace $workspace): int
    {
        return (int) $workspace->crmAssignmentRules()->max('position') + 1;
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmAssignmentRule
    {
        return $workspace->crmAssignmentRules()->create($data);
    }

    public function update(CrmAssignmentRule $rule, array $data): CrmAssignmentRule
    {
        $rule->update($data);

        return $rule;
    }

    public function delete(CrmAssignmentRule $rule): void
    {
        $rule->delete();
    }

    /** Puts its rules in the order of $ids, every one of its own. */
    public function reorder(Workspace $workspace, array $ids): void
    {
        foreach (array_values($ids) as $position => $id) {
            $workspace->crmAssignmentRules()->whereKey($id)->update(['position' => $position + 1]);
        }
    }

    /** The ids of its rules. @return list<int> */
    public function idsFor(Workspace $workspace): array
    {
        return $workspace->crmAssignmentRules()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** $rule's row, locked until the lead it routes is saved: two leads at once take their turns one after the other. */
    public function lock(CrmAssignmentRule $rule): CrmAssignmentRule
    {
        return CrmAssignmentRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
    }

    /** Moves $rule's turn on to $userId, quietly: a turn is not a change to the rule. */
    public function tookTurn(CrmAssignmentRule $rule, int $userId): void
    {
        CrmAssignmentRule::query()->whereKey($rule->id)->toBase()->update(['last_user_id' => $userId]);
    }
}
