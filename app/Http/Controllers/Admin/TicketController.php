<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListTicketsRequest;
use App\Http\Requests\Admin\NewTicketRequest;
use App\Http\Requests\Admin\ReplyTicketRequest;
use App\Http\Requests\Admin\StoreTicketRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Services\Admin\TicketService;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
    ) {}

    /**
     * The support cases: by status, words, or one business's (Doc 15 section 15.22).
     */
    public function index(ListTicketsRequest $request)
    {
        try {
            $tickets  = $this->ticketService->getTickets($request->validated());
            $business = $this->ticketService->findBusiness($request->validated('workspace'));

            return view('admin.tickets.index', compact('tickets', 'business'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * The new-case form: for someone in a business when opened from its page.
     */
    public function create(NewTicketRequest $request)
    {
        try {
            $business = $this->ticketService->findBusiness($request->validated('workspace'));
            $users    = $this->ticketService->getUsers($business);
            $staff    = $this->ticketService->getStaff();

            return view('admin.tickets.create', compact('users', 'staff', 'business'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request)
    {
        try {
            $ticket = $this->ticketService->createTicket(
                admin: $request->user(),
                userId: (int) $request->validated('user_id'),
                subject: $request->validated('subject'),
                priority: $request->validated('priority'),
                message: $request->validated('message'),
                assignedTo: $request->validated('assigned_to'),
                workspaceId: $request->validated('workspace_id'),
            );

            return redirect()->route('admin.tickets.show', $ticket->id)
                ->with('success', 'Ticket created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Display the specified ticket conversation.
     */
    public function show(int $id)
    {
        try {
            $ticket = $this->ticketService->find($id);

            if (! $ticket) {
                abort(404);
            }

            $staff            = $this->ticketService->getStaff();
            $requesterTickets = $this->ticketService->requesterTicketCount($ticket);

            return view('admin.tickets.show', compact('ticket', 'staff', 'requesterTickets'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a reply to the ticket.
     */
    public function reply(ReplyTicketRequest $request, int $id)
    {
        try {
            $ticket = $this->ticketService->find($id);
            if (! $ticket) {
                abort(404);
            }

            $this->ticketService->reply(
                $ticket,
                $request->message,
                $this->ticketService->storeAttachments($request->file('attachments', []))
            );

            return back()->with('success', 'Reply sent successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update the specified ticket.
     */
    public function update(UpdateTicketRequest $request, int $id)
    {
        try {
            $ticket = $this->ticketService->find($id);
            if (! $ticket) {
                abort(404);
            }

            $this->ticketService->updateTicket($ticket, $request->only(['status', 'assigned_to']));

            return back()->with('success', 'Ticket updated.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
