<?php

namespace App\Services\Crm;

use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\Workspace;
use App\Repositories\User\CrmPipelineRepository;
use App\Services\Api\Crm\CrmSettingService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Which pipeline an opportunity goes in (Doc 17 section 17.13, Phase D3b):
 * one switched on, for its sales team and location - for all of them, or for
 * theirs among those chosen. Without one asked for, the first that fits, in
 * Settings > CRM's order. Its lists and first pipeline are started if
 * Settings > CRM never was.
 */
class PipelineChoice
{
    public function __construct(
        protected CrmPipelineRepository $pipelines,
        protected CrmSettingService $settings,
    ) {}

    /** The business's pipelines switched on, in order, with their stages. */
    public function active(Workspace $workspace): Collection
    {
        $this->settings->start($workspace);

        return $this->pipelines->getFor($workspace)->where('is_active', true)->values();
    }

    /**
     * $pipelineId's pipeline - or, without one, the first that fits - for an
     * opportunity of $teamId at $locationId. Refused against $field when it is
     * not one of the business's switched on, or not for that team and location.
     */
    public function choose(Workspace $workspace, ?int $pipelineId, ?int $teamId, ?int $locationId, string $field = 'pipeline_id'): CrmPipeline
    {
        $fitting = $this->active($workspace)->filter(fn (CrmPipeline $pipeline) => $pipeline->fits($teamId, $locationId));
        $chosen  = $pipelineId ? $fitting->firstWhere('id', $pipelineId) : $fitting->first();

        return $chosen ?? throw ValidationException::withMessages([$field => $pipelineId
            ? 'Choose a pipeline switched on that is for its sales team and location.'
            : 'No pipeline switched on is for its sales team and location. Settings → CRM sets whom each is for.']);
    }

    /** @return Collection<int, CrmStage> $pipeline's open stages switched on, in order */
    public function openStages(CrmPipeline $pipeline): Collection
    {
        return $pipeline->stages->filter(fn (CrmStage $stage) => $stage->is_active && $stage->category === CrmStage::OPEN)->values();
    }

    /**
     * Each pipeline switched on - those that fit, when a team and location
     * are given - with whom it is for and its open stages: what a form offers.
     */
    public function forForms(Workspace $workspace, bool $fitting = false, ?int $teamId = null, ?int $locationId = null): array
    {
        return $this->described($this->active($workspace)->filter(fn (CrmPipeline $pipeline) => ! $fitting || $pipeline->fits($teamId, $locationId)));
    }

    /**
     * $pipelines as a form offers them, in their order.
     *
     * @return list<array{id: int, name: string, team_ids: list<int>, location_ids: list<int>, stages: list<array{id: int, name: string, probability: int}>}>
     */
    public function described(Collection $pipelines): array
    {
        return $pipelines
            ->map(fn (CrmPipeline $pipeline) => [
                'id'           => $pipeline->id,
                'name'         => $pipeline->name,
                'team_ids'     => $pipeline->team_ids ?? [],
                'location_ids' => $pipeline->location_ids ?? [],
                'stages'       => $this->openStages($pipeline)->map(fn (CrmStage $stage) => ['id' => $stage->id, 'name' => $stage->name, 'probability' => $stage->probability])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
