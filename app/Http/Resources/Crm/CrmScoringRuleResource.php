<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead scoring rule as Settings → CRM shows and edits it (Doc 17 Phase
 * I2): whether it is on, its factor with what it asks for - the source,
 * location or item named, an option or a threshold - and its points.
 */
class CrmScoringRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $named = fn ($record) => $record ? ['id' => $record->id, 'name' => $record->name] : null;

        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'is_active' => $this->is_active,
            'factor'    => $this->factor,
            'source'    => $named($this->source),
            'location'  => $named($this->location),
            'item'      => $named($this->item),
            'option'    => $this->option,
            'threshold' => $this->threshold,
            'points'    => $this->points,
        ];
    }
}
