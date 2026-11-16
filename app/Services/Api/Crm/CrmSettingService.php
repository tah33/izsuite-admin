<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOption;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\CrmPipelineRepository;
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
        protected CrmPipelineRepository $pipelines,
        protected WorkspaceRepository $workspaces,
        protected CrmLeadRepository $leads,
    ) {}

    /**
     * The settings, every list and the pipelines - the lists and the first
     * pipeline (Phase A2) started if they never were.
     *
     * @return array{settings: \App\Models\User\CrmSetting, options: \Illuminate\Database\Eloquent\Collection, pipelines: \Illuminate\Database\Eloquent\Collection}
     */
    public function show(Workspace $workspace): array
    {
        $this->start($workspace);

        return [
            'settings'  => $this->settings->forWorkspace($workspace),
            'options'   => $this->options->getFor($workspace),
            'pipelines' => $this->pipelines->getFor($workspace),
        ];
    }

    /** Whether the business works with leads (section 17.5): the leads' routes change nothing otherwise (EnsureCrmUsesLeads). */
    public function usesLeads(Workspace $workspace): bool
    {
        return $this->settings->forWorkspace($workspace)->usesLeads();
    }

    /** The lists and the first pipeline started if they never were - before a lead's form offers its sources too (Phase B1). */
    public function start(Workspace $workspace): void
    {
        if (! $this->options->existsFor($workspace)) {
            $this->options->addStarter($workspace);
        }

        if (! $this->pipelines->existsFor($workspace)) {
            $this->pipelines->addStarter($workspace);
        }
    }

    /** $data is validated (see SaveCrmSettingsRequest). */
    public function save(Workspace $workspace, array $data): array
    {
        $saved  = $this->settings->forWorkspace($workspace);
        $before = ['mode' => $saved->mode, 'qualification_required' => $saved->qualificationRequired()];

        $settings = $this->settings->save($workspace, Arr::only($data, ['mode', 'qualification_required']));
        $after    = ['mode' => $settings->mode, 'qualification_required' => $settings->qualificationRequired()];

        ActivityLogService::record('updated', 'Updated the CRM settings', $workspace, [
            'source'  => 'api',
            'fields'  => array_keys(array_filter($after, fn ($value, string $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH)),
            'changes' => SettingChanges::between($before, $after),
        ]);

        // Leads is in the sidebar only while the mode uses them.
        if ($before['mode'] !== $settings->mode) {
            $this->workspaces->bumpNavVersion($workspace);
        }

        return $this->show($workspace);
    }

    /** What a lead needs filled in before it is qualified (Phase B2a): keys of CrmLead::QUALIFYING. @return list<string> */
    public function qualificationRequired(Workspace $workspace): array
    {
        return $this->settings->forWorkspace($workspace)->qualificationRequired();
    }

    /** $data is validated (see SaveCrmOptionRequest): its kind and name. */
    public function addOption(Workspace $workspace, array $data): CrmOption
    {
        // Added to before they were ever read, the lists still start with Doc 17's examples.
        $this->start($workspace);

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

        // A lead keeps the source it came from (Doc 17 section 17.8).
        if ($option->kind === 'lead_source' && $this->leads->usesSource($option)) {
            $this->refuse('Leads came from this source, so it stays. Switch it off instead.');
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
