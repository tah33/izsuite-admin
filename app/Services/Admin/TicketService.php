<?php

namespace App\Services\Admin;

use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Repositories\User\TicketRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TicketService
{
    public function __construct(
        protected TicketRepository $ticketRepo,
    ) {}

    /**
     * Get tickets filtered by status/search.
     */
    public function getTickets(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->ticketRepo->getPaginated($filters, $perPage);
    }

    /**
     * Find ticket by ID.
     */
    public function find(int $id): ?Ticket
    {
        return $this->ticketRepo->findWithMessages($id);
    }

    /**
     * Get regular users for ticket creation.
     */
    public function getUsers(): Collection
    {
        return $this->ticketRepo->getUsers();
    }

    /**
     * Get staff members for assignment.
     */
    public function getStaff(): Collection
    {
        return $this->ticketRepo->getStaff();
    }

    /**
     * Create a new ticket from admin.
     */
    public function createTicket(int $userId, string $subject, string $priority, string $message, ?int $assignedTo = null): Ticket
    {
        $ticket = $this->ticketRepo->create([
            'user_id'     => $userId,
            'subject'     => $subject,
            'priority'    => $priority,
            'assigned_to' => $assignedTo,
        ]);

        // Create the initial message
        $this->ticketRepo->addMessage($ticket, [
            'user_id' => $userId,
            'message' => $message,
        ]);

        return $ticket;
    }

    /**
     * Admin reply to a ticket.
     */
    public function reply(Ticket $ticket, string $message, ?array $attachmentPaths = null): TicketMessage
    {
        $data          = [
            'user_id'         => auth()->id(),
            'message'         => $message,
            'attachment_path' => $attachmentPaths,
        ];

        // Auto-change status to 'in_progress' if currently open
        if ($ticket->status === 'open') {
            $this->ticketRepo->update($ticket, ['status' => 'in_progress']);
        }

        $ticketMessage = $this->ticketRepo->addMessage($ticket, $data);

        ActivityLogService::record('replied', "Replied to ticket #{$ticket->id}", $ticket);

        return $ticketMessage;
    }

    /**
     * Update ticket attributes (status, assigned_to, etc.).
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $result = $this->ticketRepo->update($ticket, $data);

        ActivityLogService::record('updated', "Updated ticket #{$ticket->id}", $ticket, $data);

        return $result;
    }
}
