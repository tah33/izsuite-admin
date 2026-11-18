<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What converting a lead offers (Doc 17 Phase C1): the contacts that may be
 * the lead's with what matched, (Phase D3b1) the pipelines switched on with
 * their stages, what the opportunity starts with, and whether a new contact
 * may be made from it.
 */
class CrmLeadConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'matches'            => $this->resource['matches'],
            'pipelines'          => CrmPipelineChoiceResource::collection($this->resource['pipelines']),
            'can_create_contact' => $this->resource['can_create_contact'],
            'defaults'           => $this->resource['defaults'],
        ];
    }
}
