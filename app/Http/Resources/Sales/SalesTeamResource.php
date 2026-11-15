<?php

namespace App\Http\Resources\Sales;

use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A sales team as Settings → Sales and Settings → CRM list it (Doc 16 Phase A, Doc 17 Phase A2): its manager and members, and how many documents name it - one named is switched off, not deleted. */
class SalesTeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'is_active'       => $this->is_active,
            'manager'         => $this->manager ? ['id' => $this->manager->id, 'name' => $this->manager->name] : null,
            'members'         => $this->members->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values(),
            'documents_count' => (int) ($this->documents_count ?? 0),
        ];
    }
}
