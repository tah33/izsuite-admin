<?php

namespace App\Repositories\Shared;

use App\Models\User\User;
use App\Models\User\Workspace;

class NotificationPreferenceRepository
{
    public function forWorkspace(Workspace $workspace): ?array
    {
        return self::decode(Workspace::whereKey($workspace->id)->value('notification_preferences'));
    }

    /** @return array<int, ?array> by user id */
    public function forUsers(array $ids): array
    {
        return User::whereIn('id', $ids)->get(['id', 'notification_preferences'])
            ->mapWithKeys(fn (User $user) => [$user->id => self::decode($user->getRawOriginal('notification_preferences'))])
            ->all();
    }

    public function saveForWorkspace(Workspace $workspace, array $preferences): void
    {
        Workspace::whereKey($workspace->id)->update(['notification_preferences' => json_encode($preferences)]);
    }

    public function saveForUser(User $user, array $preferences): void
    {
        User::whereKey($user->id)->update(['notification_preferences' => json_encode($preferences)]);
    }

    private static function decode(mixed $value): ?array
    {
        return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : null);
    }
}
