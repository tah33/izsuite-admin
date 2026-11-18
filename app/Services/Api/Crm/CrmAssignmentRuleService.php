<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmAssignmentRule;
use App\Models\User\Workspace;
use App\Repositories\User\CrmAssignmentRuleRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesTeamRepository;
use App\Services\Crm\LeadAssignments;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Validation\ValidationException;

/**
 * Settings → CRM's lead assignment rules (Doc 17 section 17.9 - Phase I1):
 * listed in their order with whom they can give leads to, added at the end,
 * changed, deleted and put in another order. What a rule names is the
 * business's own: a lead source, a location, a sales team, an item, and a
 * person who may own leads. Each change is logged.
 */
class CrmAssignmentRuleService
{
    public function __construct(
        protected CrmAssignmentRuleRepository $rules,
        protected CrmOptionRepository $options,
        protected LocationRepository $locations,
        protected SalesTeamRepository $teams,
        protected ItemRepository $items,
        protected LeadAssignments $assignments,
    ) {}

    /**
     * Its rules in order, the people a rule can give leads to and the sales
     * teams switched on.
     *
     * @return array{rules: \Illuminate\Database\Eloquent\Collection, owners: list<array{id: int, name: string}>, teams: \Illuminate\Support\Collection}
     */
    public function index(Workspace $workspace): array
    {
        return [
            'rules'  => $this->rules->getFor($workspace),
            'owners' => array_map(fn (array $owner) => ['id' => $owner['id'], 'name' => $owner['name']], $this->assignments->owners($workspace)),
            'teams'  => $this->teams->forWorkspace($workspace, true),
        ];
    }

    /** $data is validated (see SaveCrmAssignmentRuleRequest); it goes last. */
    public function create(Workspace $workspace, array $data): CrmAssignmentRule
    {
        $rule = $this->rules->create($workspace, [...$this->checked($workspace, $data), 'position' => $this->rules->nextPosition($workspace)]);

        ActivityLogService::record('created', "Added the assignment rule \"{$rule->name}\"", $rule, ['source' => 'api']);

        return $this->rules->findFor($workspace, $rule->id);
    }

    public function update(Workspace $workspace, int $id, array $data): CrmAssignmentRule
    {
        $rule = $this->find($workspace, $id);
        $this->rules->update($rule, $this->checked($workspace, $data, $rule->id));

        ActivityLogService::record('updated', "Updated the assignment rule \"{$rule->name}\"", $rule, ['source' => 'api', 'changes' => SettingChanges::of($rule)]);

        return $this->rules->findFor($workspace, $rule->id);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $rule = $this->find($workspace, $id);
        $this->rules->delete($rule);

        ActivityLogService::record('deleted', "Deleted the assignment rule \"{$rule->name}\"", $rule, ['source' => 'api']);
    }

    /** Puts its rules in the order of $ids: every one of them, once each. */
    public function reorder(Workspace $workspace, array $ids): \Illuminate\Database\Eloquent\Collection
    {
        $ids = array_map('intval', $ids);
        $own = $this->rules->idsFor($workspace);

        if (count($ids) !== count($own) || array_diff($own, $ids) !== []) {
            throw ValidationException::withMessages(['ids' => 'Put every rule in the order, once each.']);
        }

        $this->rules->reorder($workspace, $ids);

        ActivityLogService::record('updated', 'Changed the order of the assignment rules', $workspace, ['source' => 'api']);

        return $this->rules->getFor($workspace);
    }

    private function find(Workspace $workspace, int $id): CrmAssignmentRule
    {
        return $this->rules->findFor($workspace, $id) ?? abort(404, 'There is no such assignment rule.');
    }

    /**
     * $data as a rule keeps it: what it names checked to be the business's
     * own, and whom it gives leads to one person or one team - a team with
     * its method, by turns unless told.
     */
    private function checked(Workspace $workspace, array $data, ?int $exceptId = null): array
    {
        $name = trim($data['name']);

        if ($this->rules->nameTaken($workspace, $name, $exceptId)) {
            throw ValidationException::withMessages(['name' => 'Another rule has this name.']);
        }

        $source = $data['source_id'] ?? null;

        if ($source && $this->options->findFor($workspace, (int) $source)?->kind !== 'lead_source') {
            throw ValidationException::withMessages(['source_id' => 'Choose one of your lead sources.']);
        }

        if (($data['location_id'] ?? null) && ! $this->locations->findFor($workspace, (int) $data['location_id'])) {
            throw ValidationException::withMessages(['location_id' => 'Choose one of your locations.']);
        }

        foreach (['sales_team_id', 'team_id'] as $field) {
            if (($data[$field] ?? null) && ! $this->teams->find($workspace, (int) $data[$field])) {
                throw ValidationException::withMessages([$field => 'Choose one of your sales teams.']);
            }
        }

        if (($data['item_id'] ?? null) && ! $this->items->findFor($workspace, (int) $data['item_id'])) {
            throw ValidationException::withMessages(['item_id' => 'Choose one of your items.']);
        }

        if (($data['user_id'] ?? null) && ! collect($this->assignments->owners($workspace))->contains('id', (int) $data['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Choose an active member who may view leads.']);
        }

        $team = isset($data['team_id']) && $data['team_id'] !== null;

        return [
            'name'          => $name,
            'is_active'     => (bool) ($data['is_active'] ?? true),
            'source_id'     => $source ?: null,
            'location_id'   => ($data['location_id'] ?? null) ?: null,
            'sales_team_id' => ($data['sales_team_id'] ?? null) ?: null,
            'country'       => ($data['country'] ?? null) ?: null,
            'item_id'       => ($data['item_id'] ?? null) ?: null,
            'user_id'       => $team ? null : (int) $data['user_id'],
            'team_id'       => $team ? (int) $data['team_id'] : null,
            'method'        => $team ? ($data['method'] ?? CrmAssignmentRule::ROUND_ROBIN) : null,
        ];
    }
}
