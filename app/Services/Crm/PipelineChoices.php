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
 * Where an opportunity can go (Doc 17 Phase D3b1): the business's pipelines
 * - started if Settings → CRM never was - and the open stages, switched on,
 * of those switched on, where one is added, converted or moved to. The
 * default pipeline is where a new one starts unless another is chosen. One
 * in a pipeline switched off carries on there: its own pipeline's stages
 * stay open to it.
 */
class PipelineChoices
{
    public function __construct(
        protected CrmPipelineRepository $pipelines,
        protected CrmSettingService $settings,
    ) {}

    /** Every pipeline in order, each with its stages. */
    public function all(Workspace $workspace): Collection
    {
        $this->settings->start($workspace);

        return $this->pipelines->getFor($workspace);
    }

    /** Those an opportunity can enter: switched on - or $own, its own - with an open stage switched on. */
    public function enterable(Workspace $workspace, ?CrmPipeline $own = null): Collection
    {
        return $this->all($workspace)
            ->filter(fn (CrmPipeline $pipeline) => ($pipeline->is_active || $pipeline->id === $own?->id) && $this->openStages($pipeline)->isNotEmpty())
            ->values();
    }

    /** Where a new one starts among $pipelines unless another is chosen: the default, else the first. */
    public function default(Collection $pipelines): ?CrmPipeline
    {
        return $pipelines->first(fn (CrmPipeline $pipeline) => $pipeline->is_default) ?? $pipelines->first();
    }

    /** $stageId's open stage, switched on, in one of $pipelines - its pipeline with it - else the refusal, on $field. */
    public function stage(Collection $pipelines, int $stageId, string $field = 'stage_id'): CrmStage
    {
        foreach ($pipelines as $pipeline) {
            $stage = $this->openStages($pipeline)->firstWhere('id', $stageId);

            if ($stage) {
                return $stage->setRelation('pipeline', $pipeline);
            }
        }

        throw ValidationException::withMessages([$field => 'Choose an open stage of a pipeline switched on.']);
    }

    /** @return Collection<int, CrmStage> $pipeline's open stages switched on, in order */
    public function openStages(CrmPipeline $pipeline): Collection
    {
        return $pipeline->stages->filter(fn (CrmStage $stage) => $stage->is_active && $stage->category === CrmStage::OPEN)->values();
    }
}
