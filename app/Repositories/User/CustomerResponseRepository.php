<?php

namespace App\Repositories\User;

use App\Models\User\CustomerResponse;
use App\Models\User\DocumentLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** What customers did through secure links (Doc 16 Phase G2). */
class CustomerResponseRepository
{
    /** Made through $link's business, about its document. */
    public function create(DocumentLink $link, array $data): CustomerResponse
    {
        return $link->workspace->customerResponses()->create([
            ...$data,
            'document_link_id' => $link->id,
            'linkable_type'    => $link->linkable_type,
            'linkable_id'      => $link->linkable_id,
        ]);
    }

    /** $record's answer through a link - the latest acceptance or refusal - if it has one. */
    public function answerFor(Model $record): ?CustomerResponse
    {
        return CustomerResponse::query()
            ->where('linkable_type', $record->getMorphClass())
            ->where('linkable_id', $record->getKey())
            ->whereIn('kind', [CustomerResponse::ACCEPTED, CustomerResponse::REJECTED])
            ->latest('id')
            ->first();
    }

    /** The comments sent about $record through its links, oldest first. */
    public function commentsFor(Model $record): Collection
    {
        return CustomerResponse::query()
            ->where('linkable_type', $record->getMorphClass())
            ->where('linkable_id', $record->getKey())
            ->where('kind', CustomerResponse::COMMENT)
            ->oldest('id')
            ->get(['id', 'name', 'body', 'created_at']);
    }
}
