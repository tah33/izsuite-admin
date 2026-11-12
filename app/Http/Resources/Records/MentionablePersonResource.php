<?php

namespace App\Http\Resources\Records;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Someone a comment may mention (Doc 14 Phase D): their user's id, which the
 * comment sends, and their name in the business. Expects a WorkspaceMember.
 */
class MentionablePersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->user_id,
            'name' => $this->name,
        ];
    }
}
