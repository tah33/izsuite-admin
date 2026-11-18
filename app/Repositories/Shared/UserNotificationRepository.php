<?php

namespace App\Repositories\Shared;

use App\Models\Shared\NotificationDelivery;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;

class UserNotificationRepository
{
    /** A new notification - or null: the user has this event's already. */
    public function create(array $data): ?UserNotification
    {
        try {
            return UserNotification::create($data);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function addDelivery(UserNotification $notification, array $data): NotificationDelivery
    {
        return $notification->deliveries()->create($data);
    }

    public function findDelivery(int $id): ?NotificationDelivery
    {
        return NotificationDelivery::with('notification.user', 'notification.workspace')->find($id);
    }

    public function updateDelivery(NotificationDelivery $delivery, array $data): void
    {
        $delivery->update($data);
    }

    /** $user's notifications in $workspace, the newest first - only the unread ones if $unread. */
    public function pageFor(User $user, Workspace $workspace, bool $unread, int $perPage): LengthAwarePaginator
    {
        return UserNotification::where('user_id', $user->id)
            ->where('workspace_id', $workspace->id)
            ->where('shown', true)
            ->when($unread, fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate($perPage);
    }

    public function unreadCount(User $user, Workspace $workspace): int
    {
        return UserNotification::where('user_id', $user->id)->where('workspace_id', $workspace->id)->where('shown', true)->whereNull('read_at')->count();
    }

    public function findFor(User $user, Workspace $workspace, int $id): ?UserNotification
    {
        return UserNotification::where('user_id', $user->id)->where('workspace_id', $workspace->id)->where('shown', true)->find($id);
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    /** Every unread notification of $user's in $workspace read now; how many there were. */
    public function markAllRead(User $user, Workspace $workspace): int
    {
        return UserNotification::where('user_id', $user->id)->where('workspace_id', $workspace->id)->where('shown', true)->whereNull('read_at')->update(['read_at' => now()]);
    }
}
