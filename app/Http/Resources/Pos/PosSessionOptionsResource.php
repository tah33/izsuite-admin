<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Sessions page's choices (Doc 18 Phase B1), as
 * PosSessionService::options() gives them: the registers the list can be
 * filtered by, each with its currency and why the user may not open a
 * session on it now (null when they may), and the user's own session not yet
 * closed, if any.
 */
class PosSessionOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mine = $this->resource['mine'];

        return [
            'registers' => collect($this->resource['registers'])->map(fn (array $row) => [
                'id'       => $row['register']->id,
                'name'     => $row['register']->name,
                'code'     => $row['register']->code,
                'currency' => $row['register']->currency,
                'refusal'  => $row['refusal'],
            ])->values(),
            // Doc 18 Phase G1: the registers' cashiers, by name.
            'cashiers'  => collect($this->resource['cashiers'] ?? [])->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])->values(),
            'mine'      => $mine ? ['id' => $mine->id, 'number' => $mine->number, 'register' => ['name' => $mine->register->name, 'code' => $mine->register->code]] : null,
        ];
    }
}
