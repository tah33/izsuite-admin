<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an activity's form chooses from (Doc 17 Phase E1): the activity
 * types switched on - each saying whether completing one needs an outcome,
 * and its key ("call" for a call) - the people it can be given to, who the
 * user is, and which of leads, opportunities and contacts they may plan one
 * for.
 */
class CrmActivityOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'types'     => $this->resource['types']->map(fn ($type) => ['id' => $type->id, 'key' => $type->key, 'name' => $type->name, 'needs_outcome' => (bool) $type->needs_outcome])->values(),
            'assignees' => array_map(fn (array $person) => ['id' => $person['id'], 'name' => $person['name']], $this->resource['assignees']),
            'user_id'   => $this->resource['user_id'],
            'kinds'     => $this->resource['kinds'],
        ];
    }
}
