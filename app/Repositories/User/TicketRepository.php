<?php

namespace App\Repositories\User;

use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Models\User\User;
use App\QueryFilters\PriorityFilter;
use App\QueryFilters\SearchFilter;
use App\QueryFilters\StatusFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

class TicketRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(Ticket::with('user'))
            ->through([
                new StatusFilter($filters['status'] ?? null),
                new PriorityFilter($filters['priority'] ?? null),
                new SearchFilter(
                    $filters['search'] ?? null,
                    columns: ['subject'],
                    relations: ['user' => ['name', 'email']],
                ),
            ])
            ->thenReturn()
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    public function findWithMessages(int $id): ?Ticket
    {
        return Ticket::with(['user', 'assignee', 'messages.user'])->find($id);
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

    public function getUsers(): Collection
    {
        return User::whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'candidate']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function getStaff(): Collection
    {
        return User::whereHas('role', fn ($q) => $q->whereNotIn('slug', ['recruiter', 'candidate']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
