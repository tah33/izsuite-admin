<?php

namespace App\Services\Api\Auth;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Login as" from the admin panel's Clients page: an admin opens a client's
 * dashboard on the website, signed in as that client.
 *
 * The admin panel makes a one-time code and sends the admin's browser to the
 * website with it; the website trades the code here for an ordinary API
 * session. The code travels in the link instead of the session's token, so the
 * token is never in a URL or the browser's history - and the code works once,
 * for a minute.
 */
class LoginAsService
{
    /** How long a link works: long enough for its tab to open. */
    public const CODE_TTL_SECONDS     = 60;

    /** How long the session it starts lasts, unless signed out sooner. */
    public const SESSION_HOURS        = 2;

    /** The answer for a used, expired or made-up code alike. */
    public const INVALID_CODE_MESSAGE = 'This sign-in link has expired or was already used. Open it again from the admin panel.';

    /** The name its sessions' tokens carry: how one is told from the client's own. */
    public const TOKEN_NAME           = 'admin-login-as';

    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /**
     * Why $client cannot be signed in to, if they cannot: only while they
     * could sign in themselves - active, with their email verified.
     */
    public function problem(User $client): ?string
    {
        if ($client->status !== 'active') {
            return 'This user\'s account is not active.';
        }

        if (is_null($client->email_verified_at)) {
            return 'This user has not verified their email yet.';
        }

        return null;
    }

    /** The website link that signs $admin in as $client, with its one-time code. */
    public function link(User $client, User $admin): string
    {
        $code = Str::random(64);

        Cache::put($this->key($code), [
            'client_id'  => $client->id,
            'admin_id'   => $admin->id,
            'admin_name' => $admin->name,
            // Made here, on the admin's own request, so it names the host they
            // are signed in on - the API's host may differ (127.0.0.1:8000
            // against izsuite-admin.test:8080), and a session cookie with it.
            'back_url'   => route('admin.overview'),
        ], self::CODE_TTL_SECONDS);

        return rtrim(config('app.frontend_url'), '/').'/login-as?code='.$code;
    }

    /**
     * Trades a code from link() for a session as its client - once. The
     * client's last login is left as it was: they did not sign in.
     *
     * @return array{user: User, token: NewAccessToken, back_url: string}
     */
    public function redeem(string $code): array
    {
        // Pulled, not read: a code is spent the moment it is tried.
        $grant  = Cache::pull($this->key($code));
        $client = $grant ? $this->userRepository->findById($grant['client_id']) : null;

        // Deleted since, or no longer a client: there is nobody to sign in as.
        if (! $client || ! $client->isUser()) {
            abort(401, self::INVALID_CODE_MESSAGE);
        }

        if ($problem = $this->problem($client)) {
            abort(403, $problem);
        }

        $expiresAt = now()->addHours(self::SESSION_HOURS);
        $token     = $this->userRepository->createToken($client, self::TOKEN_NAME, ['*'], $expiresAt);
        $backUrl   = $grant['back_url'] ?? route('admin.overview');

        // Kept for as long as the session, for backUrl() to answer /auth/me with.
        Cache::put($this->backUrlKey($token->accessToken->id), $backUrl, $expiresAt);

        // In the client's own activity log, naming the admin: an admin's own
        // activity is not logged, and it is the client's account that was used.
        ActivityLogService::record('login', 'Signed in from the admin panel', $client, [
            'source'     => 'admin',
            'admin_id'   => $grant['admin_id'],
            'admin_name' => $grant['admin_name'],
        ]);

        return ['user' => $this->userRepository->loadRole($client), 'token' => $token, 'back_url' => $backUrl];
    }

    /**
     * Where the website's "Back to admin" button goes when $user's request
     * came on a session this service started - null on the client's own.
     */
    public function backUrl(User $user): ?string
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || $token->name !== self::TOKEN_NAME) {
            return null;
        }

        return Cache::get($this->backUrlKey($token->id)) ?? route('admin.overview');
    }

    private function backUrlKey(int $tokenId): string
    {
        return 'login-as-back:'.$tokenId;
    }

    /** Hashed: someone who can read the cache cannot use the codes in it. */
    private function key(string $code): string
    {
        return 'login-as:'.hash('sha256', $code);
    }
}
