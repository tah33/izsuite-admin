<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > CRM (Doc 17 Phase A1): the mode, each list by its kind in its
 * order - (Phase A2) the pipelines and their stages - and (Phase B2a) what a
 * lead needs filled in before it is qualified, (Phase F2) whether
 * confirming a sales order made from an opportunity wins it, and (Phase
 * F3b) its e-mail templates.
 */
class CrmSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $options = $this->resource['options'];

        return [
            'mode'                   => $this->resource['settings']->mode,
            'qualification_required' => $this->resource['settings']->qualificationRequired(),
            'win_on_order'           => $this->resource['settings']->win_on_order,
            // Phase G1: the closing-soon period, in days.
            'closing_soon_days'      => $this->resource['settings']->closing_soon_days,
            'lists'     => collect(array_keys(CrmOption::KINDS))
                ->mapWithKeys(fn (string $kind) => [$kind => CrmOptionResource::collection($options->where('kind', $kind)->values())])
                ->all(),
            'pipelines' => CrmPipelineResource::collection($this->resource['pipelines']),
            // Phase F3b.
            'email_templates' => CrmEmailTemplateResource::collection($this->resource['email_templates']),
        ];
    }
}
