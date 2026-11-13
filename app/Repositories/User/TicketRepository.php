<?php

namespace App\Repositories\User;

use App\Models\Admin\Role;
use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\QueryFilters\PriorityFilter;
use App\QueryFilters\SearchFilter;
use App\QueryFilters\StatusFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

/** Admin > Tickets' support cases (Doc 15 section 15.22), and who they can be for or assigned to. */
class TicketRepository
{
    /** Newest first; searched by subject, requester or business, and filtered by status, priority and business. */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(Ticket::with(['user', 'workspace:id,name', 'latestMessage'])
                ->withCount('messages')
                ->when($filters['workspace'] ?? null, fn (Builder $query, int $id) => $query->where('workspace_id', $id)))
            ->through([
                new StatusFilter($filters['status'] ?? null),
                new PriorityFilter($filters['priority'] ?? null),
                new SearchFilter(
                    $filters['search'] ?? null,
                    columns: ['subject'],
                    relations: ['user' => ['first_name', 'last_name', 'email'], 'workspace' => ['name']],
                ),
            ])
            ->thenReturn()
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function findWithMessages(int $id): ?Ticket
    {
        return Ticket::with(['user', 'workspace:id,user_id,name,status', 'assignee', 'messages.user'])->find($id);
    }

    public function findWorkspace(int $id): ?Workspace
    {
        return Workspace::with('user:id,first_name,last_name,email')->find($id, ['id', 'user_id', 'name']);
    }

    /** How many tickets $user has opened. */
    public function countForUser(User $user): int
    {
        return $user->tickets()->count();
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

    /** Every website account (the role self-registered accounts get). */
    public function getUsers(): Collection
    {
        return User::whereHas('role', fn ($q) => $q->where('slug', 'user'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    /** The people in $workspace - its owner among them - who can open it or are invited. */
    public function membersOf(Workspace $workspace): Collection
    {
        return User::whereIn('id', $this->memberIds($workspace->id))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email']);
    }

    public function isMember(int $workspaceId, int $userId): bool
    {
        return $this->memberIds($workspaceId)->where('user_id', $userId)->exists();
    }

    /** izSuite's staff: the Super Admin and every admin role - never a website account. */
    public function getStaff(): Collection
    {
        return User::where(fn ($q) => $q
            ->where('role_id', Role::SUPER_ADMIN_ID)
            ->orWhereHas('role', fn ($q) => $q->whereNotIn('slug', Role::FRONTEND_SLUGS)))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
    }

    private function memberIds(int $workspaceId): Builder
    {
        return WorkspaceMember::query()->select('user_id')->where('workspace_id', $workspaceId)->whereIn('status', WorkspaceMember::COUNTED);
    }
}
