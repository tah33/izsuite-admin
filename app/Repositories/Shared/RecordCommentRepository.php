<?php

namespace App\Repositories\Shared;

use App\Models\Shared\RecordComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** The comments on one record of a business (Doc 14 Phase D). */
class RecordCommentRepository
{
    /** $record's comments, oldest first as a conversation reads, each with its author. */
    public function forRecord(Model $record): Collection
    {
        return $this->on($record)->with('user')->orderBy('created_at')->orderBy('id')->get();
    }

    public function findForRecord(Model $record, int $id): ?RecordComment
    {
        return $this->on($record)->find($id);
    }

    public function create(array $data): RecordComment
    {
        return RecordComment::create($data);
    }

    public function delete(RecordComment $comment): void
    {
        $comment->delete();
    }

    private function on(Model $record)
    {
        return RecordComment::query()
            ->where('workspace_id', $record->getAttribute('workspace_id'))
            ->where('commentable_type', $record->getMorphClass())
            ->where('commentable_id', $record->getKey());
    }
}
