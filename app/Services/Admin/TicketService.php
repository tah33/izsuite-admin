<?php

namespace App\Services\Admin;

use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\TicketRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Tickets: the support cases (Doc 15 section 15.22) - most opened
 * from the app about a business - answered by izSuite staff, whose replies
 * and settling reach the person who asked.
 */
class TicketService
{
    public function __construct(
        protected TicketRepository $ticketRepo,
        protected SupportCaseNotifier $notifier,
    ) {}

    /**
     * Get tickets filtered by status, words or business.
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

    /** The business a list or a new case is for, with its owner; null for none. */
    public function findBusiness(?int $id): ?Workspace
    {
        return $id ? $this->ticketRepo->findWorkspace($id) : null;
    }

    /**
     * How many tickets the ticket's requester has opened, this one included.
     */
    public function requesterTicketCount(Ticket $ticket): int
    {
        return $this->ticketRepo->countForUser($ticket->user);
    }

    /** Who a case can be for: the people in $business, or every website account. */
    public function getUsers(?Workspace $business = null): Collection
    {
        return $business ? $this->ticketRepo->membersOf($business) : $this->ticketRepo->getUsers();
    }

    /**
     * Get staff members for assignment.
     */
    public function getStaff(): Collection
    {
        return $this->ticketRepo->getStaff();
    }

    /**
     * A case $admin opens for someone - about their business, when one is
     * named - with $admin's own words as its first message.
     */
    public function createTicket(User $admin, int $userId, string $subject, string $priority, string $message, ?int $assignedTo = null, ?int $workspaceId = null): Ticket
    {
        if ($workspaceId && ! $this->ticketRepo->isMember($workspaceId, $userId)) {
            throw ValidationException::withMessages(['user_id' => 'Choose someone in this business.']);
        }

        $ticket = DB::transaction(function () use ($admin, $userId, $subject, $priority, $message, $assignedTo, $workspaceId) {
            $ticket = $this->ticketRepo->create([
                'user_id'      => $userId,
                'workspace_id' => $workspaceId,
                'subject'      => $subject,
                'priority'     => $priority,
                'assigned_to'  => $assignedTo,
            ]);

            $this->ticketRepo->addMessage($ticket, ['user_id' => $admin->id, 'by_staff' => true, 'message' => $message]);

            return $ticket;
        });

        ActivityLogService::record('created', "Opened support case #{$ticket->id}: {$subject}", $ticket, ['workspace_id' => $workspaceId], $admin);

        return $ticket;
    }

    /**
     * Stores the files attached to a reply on the public disk: their paths,
     * or null when none came.
     */
    public function storeAttachments(array $files): ?array
    {
        $paths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $file->store('ticket-attachments', 'public');
            }
        }

        return $paths ?: null;
    }

    /**
     * Staff's reply: the case moves to In Progress if it was Open, and its
     * opener hears of it.
     */
    public function reply(Ticket $ticket, string $message, ?array $attachmentPaths = null): TicketMessage
    {
        if ($ticket->status === Ticket::OPEN) {
            $this->ticketRepo->update($ticket, ['status' => Ticket::IN_PROGRESS]);
        }

        $ticketMessage = $this->ticketRepo->addMessage($ticket, [
            'user_id'         => auth()->id(),
            'by_staff'        => true,
            'message'         => $message,
            'attachment_path' => $attachmentPaths,
        ]);

        ActivityLogService::record('replied', "Replied to ticket #{$ticket->id}", $ticket, ['workspace_id' => $ticket->workspace_id]);

        $this->notifier->replied($ticket, $ticketMessage);

        return $ticketMessage;
    }

    /**
     * Update ticket attributes (status, assigned_to, etc.). Settled -
     * resolved or closed - its opener hears of it.
     */
    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        $before = $ticket->status;
        $result = $this->ticketRepo->update($ticket, $data);

        ActivityLogService::record('updated', "Updated ticket #{$ticket->id}", $ticket, [...$data, 'workspace_id' => $ticket->workspace_id]);

        if ($result->status !== $before) {
            $this->notifier->statusChanged($result);
        }

        return $result;
    }
}
