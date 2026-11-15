<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > CRM (Doc 17 Phase A1): the mode, each list by its kind in its order - and (Phase A2) the pipelines and their stages. */
class CrmSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $options = $this->resource['options'];

        return [
            'mode'      => $this->resource['settings']->mode,
            'lists'     => collect(array_keys(CrmOption::KINDS))
                ->mapWithKeys(fn (string $kind) => [$kind => CrmOptionResource::collection($options->where('kind', $kind)->values())])
                ->all(),
            'pipelines' => CrmPipelineResource::collection($this->resource['pipelines']),
        ];
    }
}
