<?php

namespace App\Http\Resources\Sales;

use App\Models\User\SalesTeam;
use App\Models\User\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What the sales form chooses a salesperson and team from (Doc 16 Phase A): the business's active members, and its teams in use with their members. */
class SalesOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'people' => $this->resource['people']->values(),
            'teams'  => $this->resource['teams']->map(fn (SalesTeam $team) => [
                'id'         => $team->id,
                'name'       => $team->name,
                'member_ids' => $team->members->map(fn (User $user) => $user->id)->values(),
            ])->values(),
        ];
    }
}
