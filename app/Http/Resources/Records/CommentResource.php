<?php

namespace App\Http\Resources\Records;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment as its record's page shows it (Doc 14 Phase D): its words, its
 * author and when, the users it mentions, and whether it is the signed-in
 * user's own, which they may delete. Expects user loaded.
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'body'     => $this->body,
            'author'   => $this->user?->name ?? 'A former user',
            'mine'     => $this->user_id !== null && $this->user_id === $request->user()?->id,
            'at'       => $this->created_at?->toIso8601String(),
            'mentions' => array_map(fn (array $person) => ['id' => $person['id'], 'name' => $person['name']], $this->mentions ?? []),
        ];
    }
}
