<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmOption;
use App\Models\User\CrmPipeline;
use App\Models\User\CrmSetting;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOpportunityRepository;
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

    /** What a save reads and writes, each on its own - Phase G3b adds the follow-up periods. */
    private const SAVED = ['mode', 'qualification_required', 'win_on_order', 'closing_soon_days', 'lead_response_days', 'recent_activity_days'];

    public function __construct(
        protected CrmSettingRepository $settings,
        protected CrmOptionRepository $options,
        protected CrmPipelineRepository $pipelines,
        protected WorkspaceRepository $workspaces,
        protected CrmLeadRepository $leads,
        protected CrmOpportunityRepository $opportunities,
        protected \App\Repositories\User\CrmActivityRepository $activities,
        protected \App\Repositories\User\CrmEmailTemplateRepository $emailTemplates,
        protected \App\Repositories\User\MarketingConsentRepository $consents,
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

        // Phase D3b: each saying whether opportunities are or were in it, so it stays.
        $pipelines = $this->pipelines->getFor($workspace);
        $used      = $this->pipelines->usedIds($pipelines);

        // A block, not an arrow: each() stops at a callback that returns false.
        $pipelines->each(function (CrmPipeline $pipeline) use ($used) {
            $pipeline->in_use = in_array($pipeline->id, $used, true);
        });

        return [
            'settings'  => $this->settings->forWorkspace($workspace),
            'options'   => $this->options->getFor($workspace),
            'pipelines' => $pipelines,
            // Phase F3b: its e-mail templates.
            'email_templates' => $this->emailTemplates->getFor($workspace),
        ];
    }

    /** Whether the business works with leads (section 17.5): the leads' routes change nothing otherwise (EnsureCrmUsesLeads). */
    public function usesLeads(Workspace $workspace): bool
    {
        return $this->settings->forWorkspace($workspace)->usesLeads();
    }

    /** Whether confirming a sales order made from an opportunity wins it (Phase F2). */
    public function winsOnOrder(Workspace $workspace): bool
    {
        return $this->settings->forWorkspace($workspace)->win_on_order;
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
        $before = $this->saved($this->settings->forWorkspace($workspace));

        $settings = $this->settings->save($workspace, Arr::only($data, self::SAVED));
        $after    = $this->saved($settings);

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

    /**
     * The closing-soon (Phase G1) and follow-up (Phase G3b) periods, in days.
     *
     * @return array{closing_soon_days: int, lead_response_days: int, recent_activity_days: int}
     */
    public function periods(Workspace $workspace): array
    {
        return $this->settings->forWorkspace($workspace)->only(['closing_soon_days', 'lead_response_days', 'recent_activity_days']);
    }

    /** SAVED's values in $settings, as the log compares them. */
    private function saved(CrmSetting $settings): array
    {
        return [...$settings->only(self::SAVED), 'qualification_required' => $settings->qualificationRequired()];
    }

    /** $data is validated (see SaveCrmOptionRequest): its kind and name. */
    public function addOption(Workspace $workspace, array $data): CrmOption
    {
        // Added to before they were ever read, the lists still start with Doc 17's examples.
        $this->start($workspace);

        $option = $this->options->create($workspace, Arr::only($data, $data['kind'] === 'activity_type' ? ['kind', 'name', 'is_active', 'needs_outcome'] : ['kind', 'name', 'is_active']));

        ActivityLogService::record('created', "Added {$this->what($option)}", $option, ['source' => 'api']);

        return $option;
    }

    /** Its name, and whether it is offered; its list stays its own. */
    public function updateOption(Workspace $workspace, int $id, array $data): CrmOption
    {
        $option = $this->find($workspace, $id);

        $this->options->update($option, Arr::only($data, $option->kind === 'activity_type' ? ['name', 'is_active', 'needs_outcome'] : ['name', 'is_active']));

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

        // A lead keeps the source it came from (Doc 17 section 17.8), and so does an opportunity (Phase D1).
        if ($option->kind === 'lead_source' && ($this->leads->usesSource($option) || $this->opportunities->usesSource($option))) {
            $this->refuse('Leads or opportunities came from this source, so it stays. Switch it off instead.');
        }

        // A lost opportunity keeps why it was lost (Phase D2a).
        if ($option->kind === 'lost_reason' && $this->opportunities->usesLostReason($option)) {
            $this->refuse('Opportunities were lost for this reason, so it stays. Switch it off instead.');
        }

        // An activity keeps its type (Phase E1).
        if ($option->kind === 'activity_type' && $this->activities->usesType($option)) {
            $this->refuse('Activities are of this type, so it stays. Switch it off instead.');
        }

        // A consent answer keeps what it was about (Phase I3).
        if ($option->kind === 'consent_purpose' && $this->consents->usesPurpose($option)) {
            $this->refuse('Consent was recorded for this purpose, so it stays. Switch it off instead.');
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
