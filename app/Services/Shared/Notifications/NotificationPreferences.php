<?php

namespace App\Services\Shared\Notifications;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\NotificationPreferenceRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\NotificationKinds;

/**
 * What a business sends and what each user receives (Doc 11 section 22, Doc
 * 12 section 22 - Doc 14 Phase B2), by kind and channel. A notification
 * goes in the app, or by e-mail, only when the business sends it and the
 * user receives it - always, for a kind that is always on (NotificationKinds).
 * A business starts with each kind's defaults; a user receives everything
 * the business sends until they turn it off.
 */
class NotificationPreferences
{
    public function __construct(
        private NotificationPreferenceRepository $preferences,
    ) {}

    /** @return array<string, array{in_app: bool, email: bool}> */
    public function business(Workspace $workspace): array
    {
        return NotificationKinds::resolve($this->preferences->forWorkspace($workspace));
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    public function user(User $user): array
    {
        return $this->users([$user->id])[$user->id] ?? NotificationKinds::resolve(null, user: true);
    }

    /** @return array<int, array<string, array{in_app: bool, email: bool}>> by user id */
    public function users(array $ids): array
    {
        return array_map(fn (?array $chosen) => NotificationKinds::resolve($chosen, user: true), $this->preferences->forUsers($ids));
    }

    /** @return array<string, array{in_app: bool, email: bool}> the business's, as saved - the kinds always on as they are */
    public function saveBusiness(Workspace $workspace, array $chosen): array
    {
        $before = $this->business($workspace);
        $after  = NotificationKinds::resolve($chosen);

        $this->preferences->saveForWorkspace($workspace, $after);

        $changed = array_keys(array_filter($after, fn (array $channels, string $kind) => $channels !== $before[$kind], ARRAY_FILTER_USE_BOTH));

        if ($changed) {
            ActivityLogService::record('updated', 'Changed which notifications the business sends: '.implode(', ', array_map(fn (string $kind) => NotificationKinds::KINDS[$kind][0], $changed)), $workspace, ['source' => 'api']);
        }

        return $after;
    }

    /** @return array<string, array{in_app: bool, email: bool}> the user's, as saved */
    public function saveUser(User $user, array $chosen): array
    {
        $after = NotificationKinds::resolve($chosen, user: true);

        $this->preferences->saveForUser($user, $after);

        return $after;
    }
}
