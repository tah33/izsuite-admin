<?php

namespace App\Http\Resources\Crm;

use App\Models\User\CrmStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A pipeline and its stages in order (Doc 17 Phase A2), as Settings > CRM edits them. */
class CrmPipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'name'   => $this->name,
            'stages' => $this->stages->map(fn (CrmStage $stage) => [
                'id'          => $stage->id,
                'name'        => $stage->name,
                'probability' => $stage->probability,
                'category'    => $stage->category,
                // Phase D3a: days before an opportunity at it is stale, and what it needs before one enters.
                'stale_after_days' => $stage->stale_after_days,
                'required_fields'  => $stage->needs(),
                'is_active'   => $stage->is_active,
            ])->values(),
        ];
    }
}
