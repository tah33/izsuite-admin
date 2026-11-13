<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Api\Profile\TwoFactorService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * An admin's session in the admin panel (Doc 15 section 15.2). Begun at the
 * sign-in page, it ends after 30 minutes without a request, when the account
 * is switched off, and when the account's sessions are ended
 * (users.sessions_valid_from): a new role or password, or two-factor reset.
 * An admin who signed in without two-factor sees only its set-up until done.
 *
 * Only a session begun at the sign-in page is timed. One made any other way
 * (a test's actingAs) is held to the account's status and ended sessions.
 */
class AdminSessionService
{
    public const IDLE_MINUTES = 30;

    public const SWITCHED_OFF = 'This account is switched off. A Super Admin can switch it on again.';

    public const ENDED        = 'Your session was ended. Sign in again.';

    public const IDLE         = 'You were signed out after 30 minutes without activity. Sign in again.';

    private const SIGNED_IN_AT = 'admin.signed_in_at';

    private const LAST_SEEN_AT = 'admin.last_seen_at';

    private const SETTING_UP   = 'admin.setting_up_two_factor';

    public function __construct(
        protected TwoFactorService $twoFactor,
        protected UserRepository $userRepository,
    ) {}

    /** Begins the session of $user, just signed in. Without two-factor, its set-up comes first. */
    public function begin(Session $session, User $user): void
    {
        $session->put([
            self::SIGNED_IN_AT => now()->getTimestamp(),
            self::LAST_SEEN_AT => now()->getTimestamp(),
            self::SETTING_UP   => ! $this->twoFactor->isOn($user),
        ]);
    }

    /** Why $user's session has ended, if it has. */
    public function endedBecause(Session $session, User $user): ?string
    {
        // Unset reads as the column's default, active: a model made and not yet reloaded.
        if (($user->status ?? 'active') !== 'active') {
            return self::SWITCHED_OFF;
        }

        if ($user->sessions_valid_from && (int) $session->get(self::SIGNED_IN_AT) < $user->sessions_valid_from->getTimestamp()) {
            return self::ENDED;
        }

        $lastSeen = $session->get(self::LAST_SEEN_AT);

        if ($lastSeen !== null && now()->getTimestamp() - (int) $lastSeen > self::IDLE_MINUTES * 60) {
            return self::IDLE;
        }

        return null;
    }

    /** Marks a timed session as in use now. */
    public function touch(Session $session): void
    {
        if ($session->has(self::LAST_SEEN_AT)) {
            $session->put(self::LAST_SEEN_AT, now()->getTimestamp());
        }
    }

    /** Whether the panel shows $user only the two-factor set-up: signed in without it. */
    public function mustSetUpTwoFactor(Session $session, User $user): bool
    {
        return (bool) $session->get(self::SETTING_UP) && ! $this->twoFactor->isOn($user);
    }

    public function twoFactorSetUp(Session $session): void
    {
        $session->forget(self::SETTING_UP);
    }

    /** Ends every admin session of $user's begun before now: they sign in again. */
    public function endAll(User $user): void
    {
        $this->userRepository->forceUpdate($user, ['sessions_valid_from' => now()]);
    }

    /** As endAll(), but the session it is called from goes on (the admin's own new password). */
    public function endAllBut(Session $session, User $user): void
    {
        $this->endAll($user);

        $session->put(self::SIGNED_IN_AT, now()->getTimestamp());
    }

    /** Ends this session: signed out, its data gone, a new form token. */
    public function end(Session $session): void
    {
        Auth::guard('web')->logout();

        $session->invalidate();
        $session->regenerateToken();
    }
}
