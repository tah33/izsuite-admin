<?php

namespace App\Listeners;

use App\Events\AppActivated;
use App\Events\AppDeactivated;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notifier;

/**
 * An app turned on or off (Doc 13 section 19, Doc 14 section 15 - Phase
 * B1): in the app, for the business's users with Manage Apps - not the one
 * who did it. Turned off by a move to a lower plan, it says so too.
 */
class NotifyAppChange
{
    public function __construct(
        private Notifier $notifier,
        private NotificationAudience $audience,
    ) {}

    public function handleActivated(AppActivated $event): void
    {
        $this->notifier->send($event->workspace, $this->audience->withPermission($event->workspace, 'settings.apps'), new Notice(
            trigger: 'apps.activated',
            key: "apps.activated:{$event->workspace->id}:{$event->app->id}:".now()->format('YmdHis'),
            title: "{$event->app->name} is turned on",
            body: 'It is in the sidebar for everyone whose role allows it.',
            link: '/apps-center',
            subject: $event->app,
        ), auth()->user());
    }

    public function handleDeactivated(AppDeactivated $event): void
    {
        $this->notifier->send($event->workspace, $this->audience->withPermission($event->workspace, 'settings.apps'), new Notice(
            trigger: 'apps.deactivated',
            key: "apps.deactivated:{$event->workspace->id}:{$event->app->id}:".now()->format('YmdHis'),
            title: "{$event->app->name} is turned off",
            body: 'Everything recorded with it is kept, and comes back when it is turned on again.',
            link: '/apps-center',
            subject: $event->app,
        ), auth()->user());
    }
}
