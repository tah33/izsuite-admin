<?php

namespace App\Services\Api\Crm;

use App\Jobs\ScoreLeads;
use App\Models\User\CrmScoringRule;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\CrmScoringRuleRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\LocationRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Settings → CRM's lead scoring rules (Doc 17 section 17.22 - Phase I2):
 * listed in the order they were added, added, changed and deleted. What a
 * rule names is the business's own: a lead source, a location, an item. Each
 * change is logged, and scores the open leads again (ScoreLeads) - a rule's
 * name too, as a lead's factors name its rules.
 */
class CrmScoringRuleService
{
    public function __construct(
        protected CrmScoringRuleRepository $rules,
        protected CrmOptionRepository $options,
        protected LocationRepository $locations,
        protected ItemRepository $items,
    ) {}

    public function index(Workspace $workspace): Collection
    {
        return $this->rules->getFor($workspace);
    }

    /** $data is validated (see SaveCrmScoringRuleRequest). */
    public function create(Workspace $workspace, array $data): CrmScoringRule
    {
        $rule = $this->rules->create($workspace, $this->checked($workspace, $data));

        ActivityLogService::record('created', "Added the scoring rule \"{$rule->name}\"", $rule, ['source' => 'api']);
        ScoreLeads::dispatch($workspace->id);

        return $this->rules->findFor($workspace, $rule->id);
    }

    public function update(Workspace $workspace, int $id, array $data): CrmScoringRule
    {
        $rule = $this->find($workspace, $id);
        $this->rules->update($rule, $this->checked($workspace, $data, $rule->id));

        ActivityLogService::record('updated', "Updated the scoring rule \"{$rule->name}\"", $rule, ['source' => 'api', 'changes' => SettingChanges::of($rule)]);
        ScoreLeads::dispatch($workspace->id);

        return $this->rules->findFor($workspace, $rule->id);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $rule = $this->find($workspace, $id);
        $this->rules->delete($rule);

        ActivityLogService::record('deleted', "Deleted the scoring rule \"{$rule->name}\"", $rule, ['source' => 'api']);
        ScoreLeads::dispatch($workspace->id);
    }

    private function find(Workspace $workspace, int $id): CrmScoringRule
    {
        return $this->rules->findFor($workspace, $id) ?? abort(404, 'There is no such scoring rule.');
    }

    /**
     * $data as a rule keeps it: only what its factor asks for
     * (CrmScoringRule::NEEDS), the rest empty, and what it names checked to
     * be the business's own.
     */
    private function checked(Workspace $workspace, array $data, ?int $exceptId = null): array
    {
        $name = trim($data['name']);

        if ($this->rules->nameTaken($workspace, $name, $exceptId)) {
            throw ValidationException::withMessages(['name' => 'Another scoring rule has this name.']);
        }

        $factor = $data['factor'];
        $field  = CrmScoringRule::NEEDS[$factor];
        $value  = $data[$field];

        $refusal = match (true) {
            $field === 'source_id' && $this->options->findFor($workspace, (int) $value)?->kind !== 'lead_source' => 'Choose one of your lead sources.',
            $field === 'location_id' && ! $this->locations->findFor($workspace, (int) $value)                      => 'Choose one of your locations.',
            $field === 'item_id' && ! $this->items->findFor($workspace, (int) $value)                              => 'Choose one of your items.',
            default                                                                                                 => null,
        };

        if ($refusal) {
            throw ValidationException::withMessages([$field => $refusal]);
        }

        return [
            'name'        => $name,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'factor'      => $factor,
            'source_id'   => null,
            'location_id' => null,
            'item_id'     => null,
            'option'      => null,
            'threshold'   => null,
            'points'      => (int) $data['points'],
            $field        => match ($field) {
                'option'    => trim((string) $value),
                'threshold' => (string) $value,
                default     => (int) $value,
            },
        ];
    }
}
