<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What converting a lead offers (Doc 17 Phase C1): the contacts that may be
 * the lead's with what matched, the pipelines for its sales team and location
 * (Phase D3b) each with its open stages and their probabilities, what the
 * opportunity starts with, and whether a new contact may be made from it.
 */
class CrmLeadConversionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'matches'            => $this->resource['matches'],
            // Already the ones for the lead's team and location: whom each is for is not needed.
            'pipelines'          => array_map(fn (array $pipeline) => ['id' => $pipeline['id'], 'name' => $pipeline['name'], 'stages' => $pipeline['stages']], $this->resource['pipelines']),
            'can_create_contact' => $this->resource['can_create_contact'],
            'defaults'           => $this->resource['defaults'],
        ];
    }
}
