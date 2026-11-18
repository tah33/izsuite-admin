<?php

namespace App\Services\Api\Auth;

use App\Models\User\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The step between a right password and a session, for an account with
 * two-factor authentication on (Doc 12 section 22): a secret the sign-in page
 * holds for a few minutes and sends back with the app's code. It remembers
 * who is signing in and how - kept signed in or not, from which device - and
 * ends after a few wrong codes, so the code cannot be guessed.
 */
class SignInChallenges
{
    public const MINUTES = 5;

    public const TRIES   = 5;

    /** A new challenge for $user: the secret the page sends back. */
    public function open(User $user, bool $remember, ?string $deviceName): string
    {
        $secret  = Str::random(40);
        $expires = now()->addMinutes(self::MINUTES);

        Cache::put($this->key($secret), [
            'user_id' => $user->id, 'remember' => $remember, 'device_name' => $deviceName, 'tries' => 0, 'expires_at' => $expires->getTimestamp(),
        ], $expires);

        return $secret;
    }

    /** @return array{user_id: int, remember: bool, device_name: ?string, tries: int, expires_at: int}|null the challenge, while it lasts */
    public function find(string $secret): ?array
    {
        return Cache::get($this->key($secret));
    }

    /** A wrong code: whether another may be tried, before the challenge's time is up. The last one ends it. */
    public function failed(string $secret, array $challenge): bool
    {
        $tries = $challenge['tries'] + 1;

        if ($tries >= self::TRIES) {
            $this->close($secret);

            return false;
        }

        Cache::put($this->key($secret), [...$challenge, 'tries' => $tries], Carbon::createFromTimestamp($challenge['expires_at']));

        return true;
    }

    public function close(string $secret): void
    {
        Cache::forget($this->key($secret));
    }

    private function key(string $secret): string
    {
        return 'sign-in-challenge:'.hash('sha256', $secret);
    }
}
