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
 * Pipelines kept in Settings > CRM (Doc 17 sections 17.13 and 17.14). A
 * pipeline is saved whole (Phase A2): its name and its stages in order, each
 * with its default probability and category - a won stage always 100% and a
 * lost one 0%, whatever is sent. A stage keeps its id when the pipeline is
 * saved, so the opportunities in it stay there.
 *
 * Since Phase D3b a business has several: one is added with the starter
 * stages, each is switched on or off and is for all sales teams and locations
 * or only those chosen, and one no opportunity was ever in is deleted. One
 * pipeline stays switched on, and one stays for every team and location, so
 * every opportunity has somewhere to go.
 */
class CrmPipelineService
{
    use \App\Services\Api\Sales\RefusesConflicts;

    public function __construct(
        protected CrmPipelineRepository $pipelines,
        protected CrmOpportunityRepository $opportunities,
    ) {}

    /** A pipeline added, with the starter stages to change. $data is validated (see CreateCrmPipelineRequest). */
    public function create(Workspace $workspace, array $data): CrmPipeline
    {
        $pipeline = DB::transaction(fn () => $this->pipelines->create($workspace, [
            'name'         => trim($data['name']),
            'is_active'    => true,
            'team_ids'     => $this->ids($data['team_ids'] ?? null),
            'location_ids' => $this->ids($data['location_ids'] ?? null),
        ], CrmPipeline::STARTER_STAGES));

        ActivityLogService::record('created', "Added the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api']);

        return $pipeline;
    }

    /** $data is validated (see SaveCrmPipelineRequest). */
    public function save(Workspace $workspace, int $id, array $data): CrmPipeline
    {
        $pipeline = $this->pipelines->findFor($workspace, $id) ?? abort(404, 'Pipeline not found.');
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

        // A stage left out is removed - unless opportunities are in it (Phase C1) or were (Phase D1): those stay, with their history.
        $removed = $pipeline->stages->pluck('id')->diff(array_filter(array_column($stages, 'id')))->values()->all();
        $inUse   = $this->opportunities->stagesInUse($removed);

        if ($inUse !== []) {
            throw ValidationException::withMessages(['stages' => 'Opportunities are or were at "'.$pipeline->stages->whereIn('id', $inUse)->pluck('name')->implode('", "').'", so it stays: switch it off instead.']);
        }

        // Phase D3b: switched on or off, and whom it is for - with one pipeline for everyone always on.
        $scope = [
            'is_active'    => (bool) ($data['is_active'] ?? $pipeline->is_active),
            'team_ids'     => array_key_exists('team_ids', $data) ? $this->ids($data['team_ids']) : $pipeline->team_ids,
            'location_ids' => array_key_exists('location_ids', $data) ? $this->ids($data['location_ids']) : $pipeline->location_ids,
        ];

        $this->ensureOneForAll($workspace, $pipeline, $scope);

        DB::transaction(function () use ($pipeline, $data, $stages, $scope) {
            $this->pipelines->update($pipeline, ['name' => trim($data['name']), ...$scope]);
            $this->pipelines->replaceStages($pipeline, $stages);
        });

        // Logged here, so LogApiWriteActivity does not add its own vaguer entry.
        ActivityLogService::record('updated', "Updated the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api', 'stages' => count($stages)]);

        $pipeline->load('stages');
        // Whether opportunities are or were in it, so it stays.
        $pipeline->in_use = $this->pipelines->isUsed($pipeline);

        return $pipeline;
    }

    /** Deleted while no opportunity is or was ever in it - otherwise it is switched off, keeping their history. */
    public function delete(Workspace $workspace, int $id): void
    {
        $pipeline = $this->pipelines->findFor($workspace, $id) ?? abort(404, 'Pipeline not found.');

        if ($this->pipelines->isUsed($pipeline)) {
            $this->refuse("Opportunities are or were in \"{$pipeline->name}\", so it stays: switch it off instead.");
        }

        $this->ensureOneForAll($workspace, $pipeline, null);

        $this->pipelines->delete($pipeline);

        ActivityLogService::record('deleted', "Deleted the pipeline \"{$pipeline->name}\"", $pipeline, ['source' => 'api']);
    }

    /**
     * Refused when, with $pipeline as $scope makes it - or gone, for null - no
     * pipeline switched on would be for every sales team and location: an
     * opportunity of any team, or of none, needs one to go to.
     */
    private function ensureOneForAll(Workspace $workspace, CrmPipeline $pipeline, ?array $scope): void
    {
        $forAll = $this->pipelines->getFor($workspace)
            ->map(fn (CrmPipeline $row) => $row->is($pipeline) ? ($scope === null ? null : (clone $row)->forceFill($scope)) : $row)
            ->filter()
            ->contains(fn (CrmPipeline $row) => $row->is_active && $row->fits(null, null));

        if (! $forAll) {
            throw ValidationException::withMessages(['is_active' => 'One pipeline switched on must stay for every sales team and location, so every opportunity has somewhere to go.']);
        }
    }

    /** Chosen ids, or null for all. */
    private function ids(?array $ids): ?array
    {
        $ids = array_values(array_unique(array_map('intval', $ids ?? [])));

        return $ids === [] ? null : $ids;
    }
}
