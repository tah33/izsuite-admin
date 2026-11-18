<?php

namespace App\Repositories\User;

use App\Models\User\CrmPipeline;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/** A business's opportunity pipelines and their stages (Doc 17 Phase A2). */
class CrmPipelineRepository
{
    /** Its pipelines in order, each with its stages. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->crmPipelines()->with('stages')->orderBy('position')->orderBy('id')->get();
    }

    public function existsFor(Workspace $workspace): bool
    {
        return $workspace->crmPipelines()->exists();
    }

    /**
     * The starting pipeline (CrmPipeline::STARTER_STAGES). One made already by
     * a request alongside - by its name - is left as it is.
     */
    public function addStarter(Workspace $workspace): void
    {
        $now  = now();
        $made = CrmPipeline::query()->insertOrIgnore([
            'workspace_id' => $workspace->id, 'name' => CrmPipeline::STARTER_NAME, 'position' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        if (! $made) {
            return;
        }

        $workspace->crmPipelines()->where('name', CrmPipeline::STARTER_NAME)->first()->stages()->createMany(array_map(
            fn (array $stage, int $index) => ['name' => $stage[0], 'probability' => $stage[1], 'category' => $stage[2], 'position' => $index + 1],
            CrmPipeline::STARTER_STAGES,
            array_keys(CrmPipeline::STARTER_STAGES),
        ));
    }

    /** A pipeline added (Phase D3b), last in order, with $stages - each [name, probability, category]. */
    public function create(Workspace $workspace, array $data, array $stages): CrmPipeline
    {
        $pipeline = $workspace->crmPipelines()->create([...$data, 'position' => ((int) $workspace->crmPipelines()->max('position')) + 1]);

        $pipeline->stages()->createMany(array_map(
            fn (array $stage, int $index) => ['name' => $stage[0], 'probability' => $stage[1], 'category' => $stage[2], 'position' => $index + 1],
            $stages,
            array_keys($stages),
        ));

        return $pipeline->load('stages');
    }

    public function delete(CrmPipeline $pipeline): void
    {
        $pipeline->delete();
    }

    /** Whether opportunities are in it, or ever were - leads converted into it included. */
    public function isUsed(CrmPipeline $pipeline): bool
    {
        return \App\Models\User\CrmOpportunity::query()->where('pipeline_id', $pipeline->id)->exists()
            || \App\Models\User\CrmStageVisit::query()->whereIn('crm_stage_id', $pipeline->stages()->select('id'))->exists();
    }

    /** Of $pipelines, the ids of those opportunities are or were in - as isUsed() decides for one. */
    public function usedIds(Collection $pipelines): array
    {
        $ids     = $pipelines->modelKeys();
        $held    = \App\Models\User\CrmOpportunity::query()->whereIn('pipeline_id', $ids)->distinct()->pluck('pipeline_id')->all();
        $visited = \App\Models\User\CrmStage::query()->whereIn('crm_pipeline_id', $ids)
            ->whereExists(fn ($query) => $query->from('crm_stage_visits')->whereColumn('crm_stage_visits.crm_stage_id', 'crm_stages.id'))
            ->distinct()->pluck('crm_pipeline_id')->all();

        return array_values(array_unique(array_map('intval', [...$held, ...$visited])));
    }

    public function findFor(Workspace $workspace, int $id): ?CrmPipeline
    {
        return $workspace->crmPipelines()->with('stages')->find($id);
    }

    public function update(CrmPipeline $pipeline, array $data): CrmPipeline
    {
        $pipeline->update($data);

        return $pipeline;
    }

    /**
     * Its stages as given, in their order. A stage sent with its id is changed
     * where it is - so what it holds keeps it - another is added, and the
     * pipeline's others go. An id sent twice is the first one's.
     */
    public function replaceStages(CrmPipeline $pipeline, array $stages): void
    {
        $kept = $pipeline->stages()->whereIn('id', array_filter(array_column($stages, 'id')))->pluck('id')->all();
        $used = [];

        $pipeline->stages()->whereNotIn('id', $kept)->delete();

        foreach (array_values($stages) as $index => $stage) {
            $id     = $stage['id'] ?? null;
            $fields = [...Arr::except($stage, 'id'), 'position' => $index + 1];

            if (in_array($id, $kept, true) && ! isset($used[$id])) {
                $used[$id] = true;
                // Through the model, so its casts write what a stage needs (Phase D3a) as JSON.
                $pipeline->stages()->whereKey($id)->first()?->update($fields);
            } else {
                $pipeline->stages()->create($fields);
            }
        }

        $pipeline->unsetRelation('stages');
    }
}
