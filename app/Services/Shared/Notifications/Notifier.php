<?php

namespace App\Services\Shared\Notifications;

use App\Jobs\DeliverNotificationEmail;
use App\Mail\NotificationMail;
use App\Models\Shared\NotificationDelivery;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\UserNotificationRepository;
use App\Support\NotificationKinds;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one way izSuite notifies the people of a business (Doc 14 section 15 -
 * Phase B1). Each user gets a notification in the app at once and - when
 * the notice says so - an e-mail through the queue, tried three times, as
 * far as the business sends and the user receives its kind (Phase B2); each
 * delivery's status and failure are kept. An event reaches a user once,
 * however often it is reported (its key). Who it reaches is the caller's
 * choice, from NotificationAudience; the user who did it is left out.
 */
class Notifier
{
    public function __construct(
        private UserNotificationRepository $notifications,
        private NotificationPreferences $preferences,
    ) {}

    /**
     * Each channel as the business sends this kind and the user receives it
     * (Phase B2): in the app, and by e-mail when the notice can go by e-mail.
     * Sent by e-mail only, it stays out of the bell; by neither, it is not
     * made at all.
     *
     * @param  iterable<User>  $users
     * @return int how many it reached for the first time
     */
    public function send(Workspace $workspace, iterable $users, Notice $notice, ?User $except = null): int
    {
        $reached  = 0;
        $users    = collect($users)->unique('id')->reject(fn (User $user) => $except && $user->id === $except->id)->values();
        $kind     = NotificationKinds::of($notice->trigger);
        $sends    = $this->preferences->business($workspace)[$kind] ?? ['in_app' => true, 'email' => true];
        $receives = $this->preferences->users($users->pluck('id')->all());

        foreach ($users as $user) {
            $mine  = $receives[$user->id][$kind] ?? ['in_app' => true, 'email' => true];
            $inApp = $sends['in_app'] && $mine['in_app'];
            $email = $notice->email && $sends['email'] && $mine['email'];

            if (! $inApp && ! $email) {
                continue;
            }

            $notification = $this->notifications->create([
                'workspace_id' => $workspace->id,
                'user_id'      => $user->id,
                'trigger'      => $notice->trigger,
                'title'        => Str::limit($notice->title, 197),
                'body'         => $notice->body,
                'link'         => $notice->link,
                'shown'        => $inApp,
                'subject_type' => $notice->subject?->getMorphClass(),
                'subject_id'   => $notice->subject?->getKey(),
                'event_key'    => Str::limit($notice->key, 150, ''),
            ]);

            // This user has it already.
            if (! $notification) {
                continue;
            }

            if ($inApp) {
                $this->notifications->addDelivery($notification, ['channel' => NotificationDelivery::IN_APP, 'status' => NotificationDelivery::SENT, 'attempts' => 1, 'sent_at' => now()]);
            }

            // The address is read when it is sent: the user given may have been loaded without it.
            if ($email) {
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

        // In the user's language, where a translation exists (Doc 14 section 15).
        $user = $delivery->notification->user;

        Mail::to($user->email)->locale($user->language ?: config('app.locale'))->send(new NotificationMail($delivery->notification));

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
