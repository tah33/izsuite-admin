<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyTicketRequest;
use App\Http\Requests\Admin\StoreTicketRequest;
use App\Http\Requests\Admin\UpdateTicketRequest;
use App\Services\Admin\TicketService;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
    ) {}

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'status' => $request->get('status'),
                'search' => $request->get('search'),
            ];

            $tickets = $this->ticketService->getTickets($filters);

            return view('admin.tickets.index', compact('tickets'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create()
    {
        try {
            $users = $this->ticketService->getUsers();
            $staff = $this->ticketService->getStaff();

            return view('admin.tickets.create', compact('users', 'staff'));

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
                userId: $request->user_id,
                subject: $request->subject,
                priority: $request->priority,
                message: $request->message,
                assignedTo: $request->assigned_to,
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
