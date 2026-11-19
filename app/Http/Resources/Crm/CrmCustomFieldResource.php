<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A custom field (Doc 17 Phase I4) as Settings → CRM shows and edits it, and
 * a lead's or opportunity's form asks it: whose it is, its name and type, a
 * choice's options, and whether it is required and switched on.
 */
class CrmCustomFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'record'      => $this->record,
            'name'        => $this->name,
            'type'        => $this->type,
            'options'     => $this->options ?? [],
            'is_required' => $this->is_required,
            'is_active'   => $this->is_active,
        ];
    }
}
