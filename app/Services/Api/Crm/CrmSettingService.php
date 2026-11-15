<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOption;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\CrmSettingRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Settings > CRM (Doc 17 section 17.26 - Phase A1):
 *
 * - The mode: leads and opportunities, or opportunities only (section 17.5),
 *   which hides Leads from the sidebar. Changing it deletes nothing.
 * - Its lists of lead sources, lost reasons and activity types (sections
 *   17.8, 17.16 and 17.17). They start the first time the business's CRM
 *   settings are read, with Doc 17's examples. An entry is renamed or
 *   switched off; one the business added can be deleted, and a starting one
 *   - izSuite's - never is.
 *
 * Each save is logged here, by name, so LogApiWriteActivity does not add its
 * own vaguer entry.
 */
class CrmSettingService
{
    use RefusesConflicts;

    public function __construct(
        protected CrmSettingRepository $settings,
        protected CrmOptionRepository $options,
        protected WorkspaceRepository $workspaces,
    ) {}

    /** @return array{settings: \App\Models\User\CrmSetting, options: \Illuminate\Database\Eloquent\Collection} the settings and every list, started if they never were */
    public function show(Workspace $workspace): array
    {
        if (! $this->options->existsFor($workspace)) {
            $this->options->addStarter($workspace);
        }

        return ['settings' => $this->settings->forWorkspace($workspace), 'options' => $this->options->getFor($workspace)];
    }

    /** $data is validated (see SaveCrmSettingsRequest). */
    public function save(Workspace $workspace, array $data): array
    {
        $before   = $this->settings->forWorkspace($workspace)->mode;
        $settings = $this->settings->save($workspace, $data);

        ActivityLogService::record('updated', 'Updated the CRM settings', $workspace, [
            'source'  => 'api',
            'fields'  => $before === $settings->mode ? [] : ['mode'],
            'changes' => SettingChanges::between(['mode' => $before], ['mode' => $settings->mode]),
        ]);

        // Leads is in the sidebar only while the mode uses them.
        if ($before !== $settings->mode) {
            $this->workspaces->bumpNavVersion($workspace);
        }

        return $this->show($workspace);
    }

    /** $data is validated (see SaveCrmOptionRequest): its kind and name. */
    public function addOption(Workspace $workspace, array $data): CrmOption
    {
        $option = $this->options->create($workspace, Arr::only($data, ['kind', 'name', 'is_active']));

        ActivityLogService::record('created', "Added {$this->what($option)}", $option, ['source' => 'api']);

        return $option;
    }

    /** Its name, and whether it is offered; its list stays its own. */
    public function updateOption(Workspace $workspace, int $id, array $data): CrmOption
    {
        $option = $this->find($workspace, $id);

        $this->options->update($option, Arr::only($data, ['name', 'is_active']));

        ActivityLogService::record('updated', "Updated {$this->what($option)}", $option, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($option->getChanges(), ['updated_at' => true])),
        ]);

        return $option;
    }

    public function deleteOption(Workspace $workspace, int $id): void
    {
        $option = $this->find($workspace, $id);

        if ($option->isStarter()) {
            $this->refuse('izSuite uses this one, so it cannot be deleted. Switch it off instead.');
        }

        $this->options->delete($option);

        ActivityLogService::record('deleted', "Deleted {$this->what($option)}", $option, ['source' => 'api']);
    }

    /** Another business's entry answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): CrmOption
    {
        return $this->options->findFor($workspace, $id) ?? abort(404, 'Not found in your CRM lists.');
    }

    /** 'lead source "Trade Show"'. */
    private function what(CrmOption $option): string
    {
        return Str::lower(Str::singular(CrmOption::KINDS[$option->kind])).' "'.Str::limit($option->name, 100).'"';
    }
}
