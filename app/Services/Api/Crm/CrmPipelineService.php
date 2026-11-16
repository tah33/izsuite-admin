<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A pipeline saved from Settings > CRM (Doc 17 sections 17.13 and 17.14 -
 * Phase A2): its name and its stages in order, each with its default
 * probability and category. A won stage is always 100% and a lost one 0%,
 * whatever is sent (section 17.14). A stage keeps its id when the pipeline
 * is saved, so the opportunities in it (Phase D) stay there.
 */
class CrmPipelineService
{
    public function __construct(
        protected CrmPipelineRepository $pipelines,
        protected CrmOpportunityRepository $opportunities,
    ) {}

    /** $data is validated (see SaveCrmPipelineRequest). */
    public function save(Workspace $workspace, int $id, array $data): CrmPipeline
    {
        $pipeline = $this->pipelines->findFor($workspace, $id) ?? abort(404, 'Pipeline not found.');
        $stages   = array_map(fn (array $stage) => [
            'id'          => isset($stage['id']) ? (int) $stage['id'] : null,
            'name'        => trim($stage['name']),
            'category'    => $stage['category'],
            'probability' => CrmStage::probabilityFor($stage['category'], (int) $stage['probability']),
            'is_active'   => (bool) ($stage['is_active'] ?? true),
        ], $data['stages']);

        // A stage left out is removed - unless opportunities are in it (Phase C1): those stay where they are.
        $removed = $pipeline->stages->pluck('id')->diff(array_filter(array_column($stages, 'id')))->values()->all();
        $inUse   = $this->opportunities->stagesInUse($removed);

        if ($inUse !== []) {
            throw ValidationException::withMessages(['stages' => 'Opportunities are in "'.$pipeline->stages->whereIn('id', $inUse)->pluck('name')->implode('", "').'", so it stays: switch it off instead.']);
        }

        DB::transaction(function () use ($pipeline, $data, $stages) {
            $this->pipelines->update($pipeline, ['name' => trim($data['name'])]);
            $this->pipelines->replaceStages($pipeline, $stages);
        });

        // Logged here, so LogApiWriteActivity does not add its own vaguer entry.
        ActivityLogService::record('updated', "Updated the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api', 'stages' => count($stages)]);

        return $pipeline->load('stages');
    }
}
