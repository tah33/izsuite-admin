<?php

namespace App\Services\Shared;

use App\Models\Shared\RecordComment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\Shared\RecordCommentRepository;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comments on a sales document, purchase or expense (Doc 14 section 14.14 -
 * Phase D), for the business alone and never printed.
 *
 * Anyone who may view the record reads and writes them; only its author
 * deletes a comment. A comment may mention the business's users who may
 * also view the record at its location. They are told through the
 * notifications (Phase B), in the app and by e-mail, as their Comments and
 * mentions setting allows. Writing and deleting are in the record's
 * activity.
 */
class RecordCommentService
{
    public function __construct(
        protected DiscussedRecords $records,
        protected RecordCommentRepository $recordCommentRepository,
        protected NotificationAudience $audience,
        protected Notifier $notifier,
    ) {}

    /** The record's comments, oldest first. */
    public function list(Workspace $workspace, string $family, ?string $kind, int $id): Collection
    {
        return $this->recordCommentRepository->forRecord($this->records->find($workspace, $family, $kind, $id)->model);
    }

    /** Who a comment on it may mention, by their names in the business: those who may view it, $user aside. */
    public function people(Workspace $workspace, string $family, ?string $kind, int $id, User $user): Collection
    {
        return $this->mentionable($workspace, $this->records->find($workspace, $family, $kind, $id))
            ->reject(fn (WorkspaceMember $member) => $member->user_id === $user->id)
            ->sortBy(fn (WorkspaceMember $member) => Str::lower((string) $member->name))
            ->values();
    }

    /** $data is validated (see SaveCommentRequest). Mentions of anyone else are dropped. */
    public function add(Workspace $workspace, string $family, ?string $kind, int $id, User $author, array $data): RecordComment
    {
        $record    = $this->records->find($workspace, $family, $kind, $id);
        $people    = $this->mentionable($workspace, $record)->keyBy('user_id');
        $mentioned = collect($data['mentions'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $id !== $author->id && $people->has($id))
            ->map(fn (int $id) => $people[$id])
            ->values();

        $comment = DB::transaction(function () use ($workspace, $record, $author, $data, $mentioned) {
            $comment = $this->recordCommentRepository->create([
                'workspace_id'     => $workspace->id,
                'commentable_type' => $record->model->getMorphClass(),
                'commentable_id'   => $record->model->getKey(),
                'user_id'          => $author->id,
                'body'             => $data['body'],
                'mentions'         => $mentioned->map(fn (WorkspaceMember $member) => ['id' => $member->user_id, 'name' => $member->name])->all() ?: null,
            ]);

            ActivityLogService::record('created', "Commented on {$record->label}", $record->model, ['source' => 'api', 'comment_id' => $comment->id]);

            return $comment;
        });

        if ($mentioned->isNotEmpty()) {
            $this->notifier->send($workspace, $mentioned->map(fn (WorkspaceMember $member) => $member->user), new Notice(
                trigger: 'comments.mentioned',
                key: "comments.mentioned:{$comment->id}",
                title: "{$author->name} mentioned you on {$record->label}",
                body: Str::limit($comment->body, 140),
                link: "{$record->link}#comments",
                subject: $record->model,
                email: true,
            ), $author);
        }

        return $comment->load('user');
    }

    public function remove(Workspace $workspace, string $family, ?string $kind, int $id, int $commentId, User $user): void
    {
        $record  = $this->records->find($workspace, $family, $kind, $id);
        $comment = $this->recordCommentRepository->findForRecord($record->model, $commentId) ?? abort(404, 'Comment not found.');

        abort_unless($comment->user_id === $user->id, 403, 'Only the person who wrote a comment can delete it.');

        DB::transaction(function () use ($record, $comment) {
            $this->recordCommentRepository->delete($comment);

            ActivityLogService::record('deleted', "Deleted a comment on {$record->label}", $record->model, ['source' => 'api', 'comment_id' => $comment->id]);
        });
    }

    /** The business's active users who may view the record, at its location - as its members, for their names in it. */
    private function mentionable(Workspace $workspace, DiscussedRecord $record): Collection
    {
        return $this->audience->membersWithPermission($workspace, $record->permission, $record->model->getAttribute('location_id'));
    }
}
