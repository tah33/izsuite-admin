<?php

namespace App\Services\Shared\Notifications;

use App\Jobs\DeliverNotificationEmail;
use App\Mail\NotificationMail;
use App\Models\Shared\NotificationDelivery;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\UserNotificationRepository;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one way izSuite notifies the people of a business (Doc 14 section 15 -
 * Phase B1). Each user gets a notification in the app at once and - when
 * the notice says so - an e-mail through the queue, tried three times; each
 * delivery's status and failure are kept. An event reaches a user once,
 * however often it is reported (its key). Who it reaches is the caller's
 * choice, from NotificationAudience; the user who did it is left out.
 */
class Notifier
{
    public function __construct(
        private UserNotificationRepository $notifications,
    ) {}

    /**
     * @param  iterable<User>  $users
     * @return int how many it reached for the first time
     */
    public function send(Workspace $workspace, iterable $users, Notice $notice, ?User $except = null): int
    {
        $reached = 0;

        foreach (collect($users)->unique('id') as $user) {
            if ($except && $user->id === $except->id) {
                continue;
            }

            $notification = $this->notifications->create([
                'workspace_id' => $workspace->id,
                'user_id'      => $user->id,
                'trigger'      => $notice->trigger,
                'title'        => Str::limit($notice->title, 197),
                'body'         => $notice->body,
                'link'         => $notice->link,
                'subject_type' => $notice->subject?->getMorphClass(),
                'subject_id'   => $notice->subject?->getKey(),
                'event_key'    => Str::limit($notice->key, 150, ''),
            ]);

            // This user has it already.
            if (! $notification) {
                continue;
            }

            $this->notifications->addDelivery($notification, ['channel' => NotificationDelivery::IN_APP, 'status' => NotificationDelivery::SENT, 'attempts' => 1, 'sent_at' => now()]);

            // The address is read when it is sent: the user given may have been loaded without it.
            if ($notice->email) {
                $delivery = $this->notifications->addDelivery($notification, ['channel' => NotificationDelivery::EMAIL, 'status' => NotificationDelivery::PENDING]);

                DeliverNotificationEmail::dispatch($delivery->id)->afterCommit();
            }

            $reached++;
        }

        return $reached;
    }

    /** Sends an e-mail delivery - once the queue runs it - and marks it sent; a failure is the queue's to try again. */
    public function deliverEmail(int $deliveryId): void
    {
        $delivery = $this->notifications->findDelivery($deliveryId);

        if (! $delivery || $delivery->status === NotificationDelivery::SENT) {
            return;
        }

        $this->notifications->updateDelivery($delivery, ['attempts' => $delivery->attempts + 1]);

        Mail::to($delivery->notification->user->email)->send(new NotificationMail($delivery->notification));

        $this->notifications->updateDelivery($delivery, ['status' => NotificationDelivery::SENT, 'sent_at' => now(), 'failure' => null]);
    }

    /** An e-mail delivery the queue gave up on: kept as failed, with why. */
    public function emailFailed(int $deliveryId, Throwable $e): void
    {
        if ($delivery = $this->notifications->findDelivery($deliveryId)) {
            $this->notifications->updateDelivery($delivery, ['status' => NotificationDelivery::FAILED, 'failure' => Str::limit($e->getMessage(), 500)]);
        }
    }
}
