<?php

namespace App\Repositories\User;

use App\Models\User\DocumentLink;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;

/** Secure links to a business's documents. */
class DocumentLinkRepository
{
    /** $record's link that still works, if it has one. */
    public function activeFor(Model $record): ?DocumentLink
    {
        return DocumentLink::query()
            ->where('linkable_type', $record->getMorphClass())
            ->where('linkable_id', $record->getKey())
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    /** A link by its token, with its document - revoked or not; the caller decides. */
    public function findByToken(string $token): ?DocumentLink
    {
        return DocumentLink::query()->with(['linkable', 'workspace'])->where('token', $token)->first();
    }

    public function tokenTaken(string $token): bool
    {
        return DocumentLink::query()->where('token', $token)->exists();
    }

    /** Made through the business, for $record. */
    public function create(Workspace $workspace, Model $record, array $data): DocumentLink
    {
        return $workspace->documentLinks()->create([
            ...$data,
            'linkable_type' => $record->getMorphClass(),
            'linkable_id'   => $record->getKey(),
        ]);
    }

    /** Every link of $record stops working. */
    public function revokeFor(Model $record): int
    {
        return DocumentLink::query()
            ->where('linkable_type', $record->getMorphClass())
            ->where('linkable_id', $record->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function recordView(DocumentLink $link): void
    {
        $link->increment('views', 1, ['last_viewed_at' => now()]);
    }
}
