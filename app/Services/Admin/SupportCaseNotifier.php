<?php

namespace App\Services\Admin;

use App\Mail\AccountNoticeMail;
use App\Models\User\Ticket;
use App\Models\User\TicketMessage;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Support\HtmlText;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Tells whoever opened a support case that izSuite answered it or settled it
 * (Doc 15 section 15.22): in the bell of the business it is about, as a
 * Message from izSuite, and by e-mail with a link to the case.
 */
class SupportCaseNotifier
{
    public function __construct(
        protected Notifier $notifier,
    ) {}

    public function replied(Ticket $ticket, TicketMessage $reply): void
    {
        $this->tell($ticket, "izSuite support replied to case #{$ticket->id}", "izsuite.case.reply.{$reply->id}", [
            "About \"{$ticket->subject}\":",
            Str::limit(HtmlText::of($reply->message), 600),
        ]);
    }

    /** Resolved or closed, it is settled; any other status says nothing. */
    public function statusChanged(Ticket $ticket): void
    {
        $words = [
            Ticket::RESOLVED => 'izSuite support marked it resolved. If it is not, write on the case and it opens again.',
            Ticket::CLOSED   => 'izSuite support closed it. If you still need help, open a new case.',
        ][$ticket->status] ?? null;

        if ($words) {
            $this->tell($ticket, "Case #{$ticket->id} is ".strtolower($ticket->statusLabel()), "izsuite.case.status.{$ticket->id}.{$ticket->status}", [
                "About \"{$ticket->subject}\":",
                $words,
            ]);
        }
    }

    /** @param  list<string>  $lines */
    private function tell(Ticket $ticket, string $title, string $key, array $lines): void
    {
        $user = $ticket->user;

        if (! $user) {
            return;
        }

        if ($ticket->workspace) {
            $this->notifier->send($ticket->workspace, [$user], new Notice(
                trigger: 'izsuite.case',
                key: $key,
                title: $title,
                body: implode(' ', $lines),
                link: "/support/{$ticket->id}",
                subject: $ticket,
            ));
        }

        Mail::to($user->email)->queue(new AccountNoticeMail(
            $user->first_name ?: $user->name, $title, $lines, 'Open the case', rtrim((string) config('app.frontend_url'), '/')."/support/{$ticket->id}",
        ));
    }
}
