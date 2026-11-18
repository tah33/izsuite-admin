<?php

namespace App\Services\Api\Support;

use App\Models\User\Ticket;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SupportCaseRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Support cases from the app (Doc 15 section 15.22): anyone in a business
 * opens one about it and writes on it; izSuite staff answer in Admin >
 * Tickets. The owner sees every case about the business, anyone else their
 * own. Their words are kept as typed and only ever shown as text.
 */
class SupportCaseService
{
    public function __construct(
        protected SupportCaseRepository $cases,
    ) {}

    public function page(Workspace $workspace, User $user, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->cases->pageFor($workspace, $user, $status, $perPage);
    }

    /** One case $user may see; anyone else's is a 404. */
    public function find(Workspace $workspace, User $user, int $id): Ticket
    {
        return $this->cases->findFor($workspace, $user, $id) ?? abort(404, 'There is no such support case.');
    }

    public function open(Workspace $workspace, User $user, string $subject, string $message): Ticket
    {
        $ticket = DB::transaction(function () use ($workspace, $user, $subject, $message) {
            $ticket = $this->cases->create([
                'workspace_id' => $workspace->id,
                'user_id'      => $user->id,
                'subject'      => $subject,
                'status'       => Ticket::OPEN,
            ]);

            $this->cases->addMessage($ticket, ['user_id' => $user->id, 'by_staff' => false, 'message' => $message]);

            return $ticket;
        });

        ActivityLogService::record('created', "Opened support case #{$ticket->id}: {$subject}", $ticket, ['source' => 'api']);

        return $this->find($workspace, $user, $ticket->id);
    }

    /** A message on the case. One resolved opens again; a closed one takes no more. */
    public function reply(Workspace $workspace, User $user, int $id, string $message): Ticket
    {
        $ticket = $this->find($workspace, $user, $id);

        abort_if($ticket->status === Ticket::CLOSED, 422, 'This case is closed. Open a new one if you still need help.');

        DB::transaction(function () use ($ticket, $user, $message) {
            if ($ticket->status === Ticket::RESOLVED) {
                $this->cases->update($ticket, ['status' => Ticket::OPEN]);
            }

            $this->cases->addMessage($ticket, ['user_id' => $user->id, 'by_staff' => false, 'message' => $message]);
        });

        ActivityLogService::record('replied', "Wrote on support case #{$ticket->id}", $ticket, ['source' => 'api']);

        return $this->find($workspace, $user, $ticket->id);
    }
}
