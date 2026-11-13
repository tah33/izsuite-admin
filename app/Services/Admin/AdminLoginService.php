<?php

namespace App\Services\Admin;

use App\Mail\AdminNewSignIn;
use App\Models\Admin\AdminSignIn;
use App\Models\User\User;
use App\Repositories\Admin\AdminSignInRepository;
use App\Repositories\User\UserRepository;
use App\Services\Api\Profile\TwoFactorService;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signing in to and out of the admin panel (Doc 15 section 15.2). Only an
 * active admin gets in: with the password, then the authenticator app's code
 * when two-factor is on. An admin without it signs in and sets it up first
 * (AdminSessionService). Wrong passwords are limited per address and IP
 * address. Every sign-in, and every attempt that failed, is kept with its IP
 * address and browser, and a sign-in from a browser the admin has not used
 * before is e-mailed to them.
 */
class AdminLoginService
{
    /** Wrong passwords allowed a minute, per address and IP address. */
    public const MAX_ATTEMPTS = 5;

    /** How long the code step waits, and the wrong codes that end it. */
    public const CODE_MINUTES = 5;

    public const CODE_TRIES   = 5;

    /** The cookie a browser is known again by: a random id, kept a year. */
    public const DEVICE_COOKIE = 'izsuite_admin_device';

    public const WRONG_PASSWORD = 'The provided credentials do not match our records.';

    public const CODE_EXPIRED   = 'The sign-in took too long. Sign in again.';

    public const TOO_MANY_CODES = 'Too many wrong codes. Sign in again.';

    public const WRONG_CODE     = 'That code is not right. Check the time on your phone, and try the newest code.';

    private const CHALLENGE = 'admin.sign_in_challenge';

    public function __construct(
        protected UserRepository $userRepository,
        protected AdminSignInRepository $signIns,
        protected TwoFactorService $twoFactor,
        protected AdminSessionService $sessions,
    ) {}

    /**
     * The admin $email and $password are for, once the address and IP
     * address are within their limit and the account is switched on.
     * Refused with a message on the e-mail field otherwise.
     */
    public function checkPassword(string $email, string $password): User
    {
        $key = 'admin-sign-in:'.sha1(mb_strtolower(trim($email)).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->failed($email, null, 'throttled');

            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $user = $this->userRepository->findByGivenEmail($email);

        if (! $user || ! $user->isAdmin() || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($key, 60);
            $this->failed($email, $user?->isAdmin() ? $user : null, 'password');

            throw ValidationException::withMessages(['email' => self::WRONG_PASSWORD]);
        }

        RateLimiter::clear($key);

        // Said only once the password is right, so it tells nobody else the account exists.
        if ($user->status !== 'active') {
            $this->failed($email, $user, 'switched_off');

            throw ValidationException::withMessages(['email' => AdminSessionService::SWITCHED_OFF]);
        }

        return $user;
    }

    /** Holds the sign-in of $user for their code when two-factor is on. Answers whether it does. */
    public function waitForCode(Session $session, User $user): bool
    {
        if (! $this->twoFactor->isOn($user)) {
            return false;
        }

        $session->put(self::CHALLENGE, [
            'user_id'    => $user->id,
            'expires_at' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
            'tries'      => 0,
        ]);

        return true;
    }

    /** The admin whose code the sign-in waits for: null when none is, or the wait is over. */
    public function awaitingCode(Session $session): ?User
    {
        $challenge = $session->get(self::CHALLENGE);

        if (! is_array($challenge) || $challenge['expires_at'] < now()->getTimestamp()) {
            $session->forget(self::CHALLENGE);

            return null;
        }

        $user = $this->userRepository->findById($challenge['user_id']);

        return $user && $user->isAdmin() && $user->status === 'active' ? $user : null;
    }

    /**
     * Checks the code that $user's sign-in waits for: from the app, or a
     * recovery code. A wrong one is refused, and the fifth ends the wait.
     */
    public function checkCode(Session $session, User $user, ?string $code, ?string $recoveryCode): void
    {
        if ($this->twoFactor->passes($user, $code, $recoveryCode) !== null) {
            $session->forget(self::CHALLENGE);

            return;
        }

        $this->failed($user->email, $user, 'two_factor');

        $challenge = $session->get(self::CHALLENGE, []);
        $tries     = ($challenge['tries'] ?? 0) + 1;

        if ($tries >= self::CODE_TRIES) {
            $session->forget(self::CHALLENGE);

            throw ValidationException::withMessages(['email' => self::TOO_MANY_CODES]);
        }

        $session->put(self::CHALLENGE, [...$challenge, 'tries' => $tries]);

        throw ValidationException::withMessages([($code === null || $code === '') ? 'recovery_code' : 'code' => self::WRONG_CODE]);
    }

    /**
     * Signs $user in, never remembered past the session, and keeps the
     * sign-in with its IP address and browser. A browser the admin has not
     * signed in on before is e-mailed to them - unless it is their first
     * sign-in of all. Answers whether two-factor must be set up first.
     */
    public function signIn(Request $request, User $user): bool
    {
        Auth::guard('web')->login($user);

        $this->sessions->begin($request->session(), $user);

        $device  = $this->device($request);
        $newOne  = $this->signIns->hasSignedIn($user) && ! $this->signIns->knowsDevice($user, $device);

        $this->signIns->create([
            'user_id'    => $user->id,
            'email'      => Str::limit(mb_strtolower($user->email), 250, ''),
            'succeeded'  => true,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'device'     => $device,
        ]);

        $this->userRepository->touchLastLogin($user);

        ActivityLogService::record('login', 'Logged into the web dashboard');

        if ($newOne) {
            Mail::to($user->email)->queue(new AdminNewSignIn(
                $user->first_name,
                now(),
                $request->ip(),
                (new AdminSignIn(['user_agent' => (string) $request->userAgent()]))->browser(),
            ));
        }

        return $this->sessions->mustSetUpTwoFactor($request->session(), $user);
    }

    public function signOut(Session $session): void
    {
        ActivityLogService::record('logout', 'Logged out from the admin panel');

        $this->sessions->end($session);
    }

    /** The hash of this browser's own random id, given one if it has none yet. */
    private function device(Request $request): string
    {
        $id = $request->cookie(self::DEVICE_COOKIE);

        if (! is_string($id) || strlen($id) !== 40) {
            $id = Str::random(40);

            Cookie::queue(self::DEVICE_COOKIE, $id, 60 * 24 * 365);
        }

        return hash('sha256', $id);
    }

    /** Keeps an attempt that failed - with the admin it was for, when it was one. */
    private function failed(string $email, ?User $user, string $failure): void
    {
        $this->signIns->create([
            'user_id'    => $user?->id,
            'email'      => Str::limit(mb_strtolower(trim($email)), 250, ''),
            'succeeded'  => false,
            'failure'    => $failure,
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit((string) request()->userAgent(), 500, ''),
        ]);
    }
}
