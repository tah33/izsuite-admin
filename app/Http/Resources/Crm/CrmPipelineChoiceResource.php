<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pipeline as an opportunity's form, the Opportunities page's filters,
 * Change Stage and Convert choose from (Doc 17 Phase D3b1): whether it is
 * the default and switched on, and its stages switched on in order, each
 * with its category, probability and what it needs before one enters.
 */
class CrmPipelineChoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'is_default' => $this->is_default,
            'is_active'  => $this->is_active,
            'stages'     => $this->stages->where('is_active', true)->map(fn (CrmStage $stage) => [
                'id'              => $stage->id,
                'name'            => $stage->name,
                'category'        => $stage->category,
                'probability'     => $stage->probability,
                'required_fields' => $stage->needs(),
            ])->values(),
        ];
    }
}
