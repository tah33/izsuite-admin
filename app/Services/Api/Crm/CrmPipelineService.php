<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business's pipelines in Settings > CRM (Doc 17 sections 17.13 and 17.14
 * - Phase A2): a pipeline's name and its stages in order, each with its
 * default probability and category. A won stage is always 100% and a lost
 * one 0%, whatever is sent (section 17.14). A stage keeps its id when the
 * pipeline is saved, so the opportunities in it stay there.
 *
 * Phase D3b1: up to CrmPipeline::MAX, each added with the starting stages.
 * One is the default, where new opportunities start: it stays switched on
 * and is not deleted. Another is switched off - taking no opportunity in -
 * or deleted when no opportunity is or was in it.
 */
class CrmPipelineService
{
    use RefusesConflicts;

    public function __construct(
        protected CrmPipelineRepository $pipelines,
        protected CrmOpportunityRepository $opportunities,
        protected CrmSettingService $settings,
    ) {}

    /** $data is validated (see AddCrmPipelineRequest). */
    public function create(Workspace $workspace, array $data): CrmPipeline
    {
        // The first pipeline is there before another, as the default.
        $this->settings->start($workspace);

        if ($this->pipelines->countFor($workspace) >= CrmPipeline::MAX) {
            throw ValidationException::withMessages(['name' => 'A business can have up to '.CrmPipeline::MAX.' pipelines.']);
        }

        $pipeline = $this->pipelines->add($workspace, trim($data['name']));

        ActivityLogService::record('created', "Added the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api', 'stages' => $pipeline->stages->count()]);

        return $this->pipelines->loadShown($pipeline);
    }

    /** $data is validated (see SaveCrmPipelineRequest). */
    public function save(Workspace $workspace, int $id, array $data): CrmPipeline
    {
        $pipeline = $this->find($workspace, $id);
        $active   = (bool) ($data['is_active'] ?? $pipeline->is_active);
        $stages   = array_map(fn (array $stage) => [
            'id'          => isset($stage['id']) ? (int) $stage['id'] : null,
            'name'        => trim($stage['name']),
            'category'    => $stage['category'],
            'probability' => CrmStage::probabilityFor($stage['category'], (int) $stage['probability']),
            // Phase D3a: only an open stage goes stale; any stage can need details before one enters it.
            'stale_after_days' => $stage['category'] === CrmStage::OPEN && ! empty($stage['stale_after_days']) ? (int) $stage['stale_after_days'] : null,
            'required_fields'  => array_values(array_intersect(array_keys(CrmStage::REQUIRABLE), $stage['required_fields'] ?? [])) ?: null,
            'is_active'   => (bool) ($stage['is_active'] ?? true),
        ], $data['stages']);

        if (! $active && $pipeline->is_default) {
            throw ValidationException::withMessages(['is_active' => 'The default pipeline stays switched on: make another one the default first.']);
        }

        // A stage left out is removed - unless opportunities are in it (Phase C1) or were (Phase D1): those stay, with their history.
        $removed = $pipeline->stages->pluck('id')->diff(array_filter(array_column($stages, 'id')))->values()->all();
        $inUse   = $this->opportunities->stagesInUse($removed);

        if ($inUse !== []) {
            throw ValidationException::withMessages(['stages' => 'Opportunities are or were at "'.$pipeline->stages->whereIn('id', $inUse)->pluck('name')->implode('", "').'", so it stays: switch it off instead.']);
        }

        DB::transaction(function () use ($pipeline, $data, $stages, $active) {
            $this->pipelines->update($pipeline, ['name' => trim($data['name']), 'is_active' => $active]);
            $this->pipelines->replaceStages($pipeline, $stages);
        });

        // Logged here, so LogApiWriteActivity does not add its own vaguer entry.
        ActivityLogService::record('updated', "Updated the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api', 'stages' => count($stages)]);

        return $this->pipelines->loadShown($pipeline);
    }

    /** Where new opportunities and converted leads start from now on: a pipeline switched on. */
    public function makeDefault(Workspace $workspace, int $id): CrmPipeline
    {
        $pipeline = $this->find($workspace, $id);

        if (! $pipeline->is_active) {
            $this->refuse('Switch this pipeline on before making it the default.');
        }

        if (! $pipeline->is_default) {
            DB::transaction(fn () => $this->pipelines->makeDefault($workspace, $pipeline));

            ActivityLogService::record('updated', "Made the pipeline \"{$pipeline->name}\" the default", $pipeline, ['source' => 'api', 'changes' => []]);
        }

        return $this->pipelines->loadShown($pipeline);
    }

    /** One no opportunity is or was in, with its stages; never the default. */
    public function delete(Workspace $workspace, int $id): void
    {
        $pipeline = $this->pipelines->loadShown($this->find($workspace, $id));

        if ($pipeline->is_default) {
            $this->refuse('The default pipeline cannot be deleted: make another one the default first.');
        }

        if ($pipeline->opportunities_exists || $pipeline->visits_exists) {
            $this->refuse('Opportunities are or were in this pipeline, so it stays. Switch it off instead.');
        }

        $this->pipelines->delete($pipeline);

        ActivityLogService::record('deleted', "Deleted the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api']);
    }

    /** Another business's pipeline answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): CrmPipeline
    {
        return $this->pipelines->findFor($workspace, $id) ?? abort(404, 'Pipeline not found.');
    }
}
