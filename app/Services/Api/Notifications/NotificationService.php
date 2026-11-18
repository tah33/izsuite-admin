<?php

namespace App\Services\Api\Notifications;

use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\UserNotificationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The signed-in user's notifications in the business (Doc 14 section 15 -
 * Phase B1), for the bell: the newest first, how many are unread, and
 * marking them read. Only ever their own.
 */
class NotificationService
{
    public function __construct(
        protected UserNotificationRepository $notifications,
    ) {}

    public function page(User $user, Workspace $workspace, bool $unread, int $perPage): LengthAwarePaginator
    {
        return $this->notifications->pageFor($user, $workspace, $unread, $perPage);
    }

    public function unreadCount(User $user, Workspace $workspace): int
    {
        return $this->notifications->unreadCount($user, $workspace);
    }

    /** Another user's notification answers like one that does not exist. */
    public function markRead(User $user, Workspace $workspace, int $id): UserNotification
    {
        $notification = $this->notifications->findFor($user, $workspace, $id) ?? abort(404, 'Notification not found.');

        return $this->notifications->markRead($notification);
    }

    public function markAllRead(User $user, Workspace $workspace): int
    {
        return $this->notifications->markAllRead($user, $workspace);
    }
}
