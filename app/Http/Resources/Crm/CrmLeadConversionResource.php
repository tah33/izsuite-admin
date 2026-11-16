<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What converting a lead offers (Doc 17 Phase C1): the contacts that may be
 * the lead's with what matched, the pipeline's open stages with their
 * probabilities, what the opportunity starts with, and whether a new contact
 * may be made from it.
 */
class CrmLeadConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'matches'            => $this->resource['matches'],
            'stages'             => $this->resource['stages']->map(fn ($stage) => ['id' => $stage->id, 'name' => $stage->name, 'probability' => $stage->probability])->values(),
            'can_create_contact' => $this->resource['can_create_contact'],
            'defaults'           => $this->resource['defaults'],
        ];
    }
}
