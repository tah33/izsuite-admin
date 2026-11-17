<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pipeline's board (Doc 17 Phase D4): the pipeline, every pipeline to
 * choose from - a switched-off one marked - and a column for each stage it
 * shows, with how many it holds, what they are worth in the base currency
 * (and how many have no rate today) and the cards it shows.
 */
class CrmOpportunityBoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'pipeline'  => ['id' => $this->resource['pipeline']->id, 'name' => $this->resource['pipeline']->name],
            'pipelines' => $this->resource['pipelines']->map(fn (CrmPipeline $pipeline) => ['id' => $pipeline->id, 'name' => $pipeline->name, 'is_active' => $pipeline->is_active])->values(),
            'columns'   => array_map(fn (array $column) => [
                'stage'   => [
                    'id'        => $column['stage']->id,
                    'name'      => $column['stage']->name,
                    'category'  => $column['stage']->category,
                    'is_active' => $column['stage']->is_active,
                ],
                'count'   => $column['count'],
                'total'   => $column['total'],
                'unrated' => $column['unrated'],
                'cards'   => CrmOpportunityCardResource::collection($column['cards']),
            ], $this->resource['columns']),
        ];
    }
}
