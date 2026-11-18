<?php

namespace App\Services\Api\Profile;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Support\DeviceName;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A user's own active sessions (Doc 12 sections 22 and 23): every device
 * signed in to the account and not expired - and signing one of them out, or
 * every one but the device in hand. Another user's sessions are not found.
 * The device in hand signs out the usual way, from the user menu.
 */
class SessionService
{
    use RefusesConflicts;

    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /** @return Collection<int, PersonalAccessToken> the most recently used first */
    public function list(User $user): Collection
    {
        return $this->userRepository->liveTokens($user);
    }

    public function signOut(User $user, int $id): void
    {
        $token = $this->userRepository->findLiveToken($user, $id);

        abort_unless($token, 404, 'There is no such session. It may have ended already.');

        if ($token->id === $user->currentAccessToken()?->id) {
            $this->refuse('This is the device you are using. Sign out from your menu instead.');
        }

        $this->userRepository->deleteToken($token);

        ActivityLogService::record('logout', 'Signed out a device: '.$this->described($token), $user, ['source' => 'api']);
    }

    /** Every session but the one the request came with - expired ones too, which end with them. */
    public function signOutOthers(User $user): void
    {
        $ended = $this->userRepository->revokeTokens($user, $user->currentAccessToken());

        ActivityLogService::record('logout', 'Signed out of every other device', $user, ['source' => 'api', 'revoked_tokens' => $ended]);
    }

    /** "Chrome on Windows, 203.0.113.7" - or what is known of it. */
    private function described(PersonalAccessToken $token): string
    {
        return implode(', ', array_filter([DeviceName::from($token->user_agent) ?? 'an unknown device', $token->ip_address]));
    }
}
