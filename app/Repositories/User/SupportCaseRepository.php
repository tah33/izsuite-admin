<?php

namespace App\Repositories\User;

use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * A business's support cases as the app sees them (Doc 15 section 15.22):
 * its owner sees every case about it, anyone else in it the cases they
 * opened.
 */
class SupportCaseRepository
{
    /** Newest activity first, each with who opened it and whether izSuite wrote last. */
    public function pageFor(Workspace $workspace, User $user, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->visible($workspace, $user)
            // latestMessage whole: a column list is ambiguous in latestOfMany's join.
            ->with(['user:id,first_name,last_name', 'latestMessage'])
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->paginate($perPage);
    }

    /** One case $user may see, with its messages and their writers; null when there is none. */
    public function findFor(Workspace $workspace, User $user, int $id): ?Ticket
    {
        return $this->visible($workspace, $user)
            ->with(['user:id,first_name,last_name', 'messages' => fn ($query) => $query->orderBy('id')->with('user:id,first_name,last_name')])
            ->find($id);
    }

    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    public function addMessage(Ticket $ticket, array $data): TicketMessage
    {
        return $ticket->messages()->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);

        return $ticket;
    }

    private function visible(Workspace $workspace, User $user): Builder
    {
        return Ticket::query()
            ->where('workspace_id', $workspace->id)
            ->when($workspace->user_id !== $user->id, fn (Builder $query) => $query->where('user_id', $user->id));
    }
}
