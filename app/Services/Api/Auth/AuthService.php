<?php

namespace App\Services\Api\Auth;

use App\Events\UserRegistered;
use App\Mail\PasswordChanged;
use App\Models\User\User;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\User\UserRepository;
use App\Services\Api\Profile\TwoFactorService;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use RuntimeException;

class AuthService
{
    /** Token name used when the caller does not label its device. */
    private const DEFAULT_DEVICE_NAME         = 'izsuite-frontend';

    /** Token lifetime when "remember me" is off. */
    private const SESSION_TOKEN_HOURS         = 12;

    /** Token lifetime when "remember me" is on. */
    private const REMEMBERED_TOKEN_DAYS       = 30;

    /** Role every self-registered account gets. Deliberately not an admin one. */
    private const FRONTEND_ROLE_SLUG          = 'user';

    /** The activity log's action for a failed sign-in to an account. */
    public const FAILED_SIGN_IN               = 'login_failed';

    /**
     * Shared by every way this endpoint can refuse a login: unknown email,
     * wrong password, or an admin account. One wording for all three so none
     * of them tells the caller which reason applied.
     */
    private const INVALID_CREDENTIALS_MESSAGE = 'The provided credentials do not match our records.';

    public function __construct(
        protected UserRepository $userRepository,
        protected EmailVerificationService $emailVerification,
        protected RoleRepository $roleRepository,
        protected TwoFactorService $twoFactor,
        protected SignInChallenges $challenges,
    ) {}

    /**
     * Create a frontend account and email it an activation code.
     *
     * No token comes back. The account is unverified until the code returns,
     * and login() refuses unverified accounts - so issuing a session here
     * would be a way around the very check this flow exists to add.
     *
     * What the account starts with - the "Billing Starter" workspace, the "Main
     * Location", and the owner as a staff member - is not made here: queued
     * jobs make it (see QueueNewAccountSetup), so registration takes no longer
     * for it. By the time the code from the e-mail has been typed in, it is there.
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = $this->userRepository->create([
                'prefix'            => $data['prefix'] ?? null,
                'first_name'        => $data['first_name'],
                'last_name'         => $data['last_name'] ?? null,
                'username'          => $data['username'],
                'email'             => $data['email'],
                'password'          => $data['password'],
                'role_id'           => $this->frontendRoleId(),
                'status'            => 'active',

                // The form's checkbox was ticked; record when, because a boolean
                // cannot say which version of the terms was in force.
                'terms_accepted_at' => now(),
            ]);

            return $user;
        });

        // The request is unauthenticated at this point, so the log service has
        // no actor to read from auth(); passing the user as the subject is what
        // fills in user_id.
        ActivityLogService::record('created', 'Registered an izSuite account', $user, [
            'source' => 'api',
        ]);

        // Announced as soon as the account is saved, ahead of the e-mail: should
        // anything after this fail, the account still gets what it starts with.
        // The listeners only leave jobs on the queue here, so this costs nothing
        // noticeable - and logged after the line above, what they make follows
        // "Registered" in the account's activity log rather than racing ahead of it.
        UserRegistered::dispatch($user->id);

        $this->emailVerification->issue($user);

        return $this->userRepository->loadRole($user);
    }

    /**
     * An account made from an invitation (Doc 12 section 6: "A new user
     * creates login credentials after accepting"), signed in at once.
     *
     * The invitation's link came to $email, so the address is taken as
     * verified. No business of its own is made - the account joins the one
     * that invited it, and can add its own later.
     *
     * @return array{user: User, token: NewAccessToken}
     */
    public function registerInvited(string $email, array $data): array
    {
        $user = $this->userRepository->create([
            'first_name'        => $data['first_name'],
            'last_name'         => $data['last_name'] ?? null,
            'username'          => $this->usernameFrom($email),
            'email'             => $email,
            'password'          => $data['password'],
            'role_id'           => $this->frontendRoleId(),
            'status'            => 'active',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),

            // The invitation's phone, language and time zone, where it gave them
            // (Doc 12 section 6) - the user's own from then on (section 22).
            ...array_filter(Arr::only($data, ['phone', 'language', 'timezone']), fn ($value) => $value !== null && $value !== ''),
        ]);

        ActivityLogService::record('created', 'Registered an izSuite account from an invitation', $user, ['source' => 'api']);

        $token = $this->userRepository->createToken($user, self::DEFAULT_DEVICE_NAME, ['*'], $this->tokenExpiry(false), request()->ip(), request()->userAgent());

        return ['user' => $this->userRepository->loadRole($this->userRepository->updateLoginMeta($user)), 'token' => $token];
    }

    /**
     * Exchange email + password for a Sanctum access token - or, with two-
     * factor authentication on (Doc 12 section 22), for a challenge the app's
     * code answers through loginWithCode().
     *
     * @return array{user: User, token: NewAccessToken}|array{user: User, challenge: string}
     */
    public function login(array $data): array
    {
        $user  = $this->userRepository->findByEmail($data['email']);

        // An unknown email and a wrong password return the identical 401 so the
        // endpoint cannot be used to discover which addresses are registered.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user && ! $user->isAdmin()) {
                $this->recordFailure($user, 'password');
            }

            abort(401, self::INVALID_CREDENTIALS_MESSAGE);
        }

        // Admins sign in through the session-based panel, not this API - but
        // saying so here would tell someone who already has the password that
        // the account is an admin's. Answered exactly like a login that never
        // matched, even though the password just did.
        if ($user->isAdmin()) {
            abort(401, self::INVALID_CREDENTIALS_MESSAGE);
        }

        if ($user->status === User::LOCKED) {
            abort(403, 'This account is locked. Contact izSuite support to unlock it.');
        }

        if ($user->status !== User::ACTIVE) {
            abort(403, 'Your account is not active. Please contact support.');
        }

        // Checked after status, because verifying an email would not help an
        // account an admin has switched off.
        if (is_null($user->email_verified_at)) {
            $this->rejectUnverifiedEmail();
        }

        // The password alone is not enough with two-factor authentication on.
        if ($this->twoFactor->isOn($user)) {
            return ['user' => $user, 'challenge' => $this->challenges->open($user, (bool) ($data['remember'] ?? false), $data['device_name'] ?? null)];
        }

        return $this->signIn($user, (bool) ($data['remember'] ?? false), $data['device_name'] ?? null);
    }

    /**
     * The second step of a sign-in with two-factor authentication on: the
     * challenge login() gave, answered with the app's code or a recovery
     * code. A few wrong codes end the challenge, and the password is asked
     * for again.
     *
     * @return array{user: User, token: NewAccessToken}
     */
    public function loginWithCode(array $data): array
    {
        $challenge = $this->challenges->find($data['challenge']);
        $user      = $challenge ? $this->userRepository->find($challenge['user_id']) : null;

        if (! $user || $user->status !== 'active' || ! $this->twoFactor->isOn($user)) {
            throw ValidationException::withMessages(['challenge' => 'This sign-in has ended. Enter your password again.']);
        }

        $via = $this->twoFactor->passes($user, $data['code'] ?? null, $data['recovery_code'] ?? null);

        if ($via === null) {
            $this->recordFailure($user, 'two_factor');

            if (! $this->challenges->failed($data['challenge'], $challenge)) {
                throw ValidationException::withMessages(['challenge' => 'Too many wrong codes. Enter your password again.']);
            }

            throw ValidationException::withMessages([
                empty($data['code']) ? 'recovery_code' : 'code' => empty($data['code'])
                    ? 'That recovery code is not right, or was used already.'
                    : 'That code is not right. Try the newest one from your app.',
            ]);
        }

        $this->challenges->close($data['challenge']);

        return $this->signIn($user, $challenge['remember'], $challenge['device_name'], ['two_factor' => $via]);
    }

    /**
     * A new session for $user, for twelve hours or, kept signed in, thirty
     * days - with where it signs in from, so the user can tell their devices
     * apart (Doc 12 section 22).
     *
     * @return array{user: User, token: NewAccessToken}
     */
    private function signIn(User $user, bool $remember, ?string $deviceName, array $logged = []): array
    {
        $token = $this->userRepository->createToken(
            $user,
            $deviceName ?? self::DEFAULT_DEVICE_NAME,
            ['*'],
            $this->tokenExpiry($remember),
            request()->ip(),
            request()->userAgent(),
        );

        $user  = $this->userRepository->updateLoginMeta($user);

        // Passing the user as the subject is what fills in user_id: at this
        // point the request itself is still unauthenticated, so the log service
        // has no actor to read from auth().
        ActivityLogService::record('login', 'Signed in from the izSuite frontend', $user, [
            'source'     => 'api',
            'token_name' => $token->accessToken->name,
            ...$logged,
        ]);

        return ['user' => $this->userRepository->loadRole($user), 'token' => $token];
    }

    /**
     * A sign-in to $user's account that failed - a wrong password, or a wrong
     * code - kept against the account, with the IP address. Admin > Users
     * counts them as a security alert (Doc 15 section 15.7). The answer to the
     * request stays the same as for an unknown address.
     */
    private function recordFailure(User $user, string $reason): void
    {
        ActivityLogService::record(self::FAILED_SIGN_IN, $reason === 'password' ? 'A sign-in failed: wrong password' : 'A sign-in failed: wrong code', $user, [
            'source' => 'api',
            'reason' => $reason,
        ]);
    }

    /** The account holding the request's token, with its role, for the client. */
    public function currentUser(User $user): User
    {
        return $this->userRepository->loadRole($user);
    }

    /**
     * Revoke only the token that made the request, leaving the user's other
     * devices signed in.
     */
    public function logout(User $user): void
    {
        $this->userRepository->revokeCurrentToken($user);

        ActivityLogService::record('logout', 'Signed out of the izSuite frontend', $user, [
            'source' => 'api',
        ]);
    }

    /**
     * Change the password of an account that is already signed in.
     *
     * Every other device is signed out, but not this one. People change a
     * password because they think someone else may have it, so leaving the
     * other sessions alive would leave the problem alive - and signing this
     * one out as well would just make them log straight back in.
     *
     * @return int Sessions ended, so the caller can say how many.
     */
    public function changePassword(User $user, string $password): int
    {
        $revoked = $this->userRepository->revokeTokens($user, $user->currentAccessToken());

        $this->userRepository->forceUpdate($user, ['password' => $password]);

        ActivityLogService::record('updated', 'Changed their password', $user, [
            'source'         => 'api',
            'revoked_tokens' => $revoked,
        ]);

        try {
            Mail::to($user->email)->send(new PasswordChanged($user->first_name, $user->email, now()));
        } catch (\Throwable $e) {
            report($e);
        }

        return $revoked;
    }

    /**
     * Revoke every token the user holds, this request's included.
     *
     * What "sign out everywhere" is for: a lost phone, a shared computer, or a
     * password change. Returns the number of sessions ended so the caller can
     * say so rather than leaving the user guessing whether it worked.
     */
    public function logoutFromAllDevices(User $user): int
    {
        $revoked = $this->userRepository->revokeTokens($user);

        ActivityLogService::record('logout', 'Signed out of every izSuite device', $user, [
            'source'         => 'api',
            'revoked_tokens' => $revoked,
        ]);

        return $revoked;
    }

    /**
     * Refuse an unverified account.
     *
     * Thrown as a response rather than via abort() because this rejection
     * carries an "action" alongside the message: the frontend has to tell this
     * apart from the other 403s to offer a "resend verification email" button
     * instead of a dead end.
     *
     * Shape and wording match CheckEmailVerified, so a client that already
     * handles that middleware needs no second code path.
     */
    private function rejectUnverifiedEmail(): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Your email address is not verified. Please verify your email to access this resource.',
            'action'  => 'verification_required',
        ], 403));
    }

    /**
     * users.role_id is NOT NULL, so there is no "role-less account" to fall
     * back to. A missing role is a deployment fault, not something a
     * registering visitor can fix, so say what is wrong here rather than let
     * the insert die on a constraint violation with nothing to act on.
     */
    private function frontendRoleId(): int
    {
        $roleId = $this->roleRepository->idOf(self::FRONTEND_ROLE_SLUG);

        if (! $roleId) {
            throw new RuntimeException(sprintf(
                'The "%s" role is missing. Run RoleSeeder before accepting registrations.',
                self::FRONTEND_ROLE_SLUG,
            ));
        }

        return $roleId;
    }

    /** "amina.rahman" from amina.rahman@example.com - numbered on while taken: amina.rahman2. */
    private function usernameFrom(string $email): string
    {
        $base = substr(trim((string) preg_replace('/[^a-z0-9._-]/', '', strtolower(strstr($email, '@', true) ?: $email)), '._-'), 0, 26);
        $base = strlen($base) >= 3 ? $base : 'user'.$base;
        $name = $base;

        for ($n = 2; $this->userRepository->usernameExists($name); $n++) {
            $name = $base.$n;
        }

        return $name;
    }

    private function tokenExpiry(bool $remember): Carbon
    {
        return $remember
            ? now()->addDays(self::REMEMBERED_TOKEN_DAYS)
            : now()->addHours(self::SESSION_TOKEN_HOURS);
    }
}
