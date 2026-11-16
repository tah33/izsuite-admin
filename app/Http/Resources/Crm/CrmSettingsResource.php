<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > CRM (Doc 17 Phase A1): the mode, each list by its kind in its
 * order - (Phase A2) the pipelines and their stages - and (Phase B2a) what a
 * lead needs filled in before it is qualified.
 */
class CrmSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $options = $this->resource['options'];

        return [
            'mode'                   => $this->resource['settings']->mode,
            'qualification_required' => $this->resource['settings']->qualificationRequired(),
            'lists'     => collect(array_keys(CrmOption::KINDS))
                ->mapWithKeys(fn (string $kind) => [$kind => CrmOptionResource::collection($options->where('kind', $kind)->values())])
                ->all(),
            'pipelines' => CrmPipelineResource::collection($this->resource['pipelines']),
        ];
    }
}
