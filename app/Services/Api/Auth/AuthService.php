<?php

namespace App\Services\Api\Auth;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use RuntimeException;

class AuthService
{
    /** Token name used when the caller does not label its device. */
    private const DEFAULT_DEVICE_NAME   = 'izsuite-frontend';

    /** Token lifetime when "remember me" is off. */
    private const SESSION_TOKEN_HOURS   = 12;

    /** Token lifetime when "remember me" is on. */
    private const REMEMBERED_TOKEN_DAYS = 30;

    /** Role every self-registered account gets. Deliberately not an admin one. */
    private const FRONTEND_ROLE_SLUG    = 'user';

    public function __construct(
        protected UserRepository $userRepository,
        protected EmailVerificationService $emailVerification,
    ) {}

    /**
     * Create a frontend account and email it an activation code.
     *
     * No token comes back. The account is unverified until the code returns,
     * and login() refuses unverified accounts - so issuing a session here
     * would be a way around the very check this flow exists to add.
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

        $this->emailVerification->issue($user);

        // The request is unauthenticated at this point, so the log service has
        // no actor to read from auth(); passing the user as the subject is what
        // fills in user_id.
        ActivityLogService::record('created', 'Registered an izSuite account', $user, [
            'source' => 'api',
        ]);

        return $user;
    }

    /**
     * Exchange email + password for a Sanctum access token.
     *
     * @return array{user: User, token: NewAccessToken}
     */
    public function login(array $data): array
    {
        $user  = $this->userRepository->findByEmail($data['email']);

        // An unknown email and a wrong password return the identical 401 so the
        // endpoint cannot be used to discover which addresses are registered.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            abort(401, 'The provided credentials do not match our records.');
        }

        // Only reachable once the password is verified, so the narrower messages
        // below leak nothing about accounts the caller does not already own.
        if ($user->isAdmin()) {
            abort(403, 'Admin accounts sign in through the admin panel, not this API.');
        }

        if ($user->status !== 'active') {
            abort(403, 'Your account is not active. Please contact support.');
        }

        // Checked after status, because verifying an email would not help an
        // account an admin has switched off.
        if (is_null($user->email_verified_at)) {
            $this->rejectUnverifiedEmail();
        }

        $token = $user->createToken(
            $data['device_name'] ?? self::DEFAULT_DEVICE_NAME,
            ['*'],
            $this->tokenExpiry((bool) ($data['remember'] ?? false)),
        );

        $user  = $this->userRepository->updateLoginMeta($user);

        // Passing the user as the subject is what fills in user_id: at this
        // point the request itself is still unauthenticated, so the log service
        // has no actor to read from auth().
        ActivityLogService::record('login', 'Signed in from the izSuite frontend', $user, [
            'source'     => 'api',
            'token_name' => $token->accessToken->name,
        ]);

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Revoke only the token that made the request, leaving the user's other
     * devices signed in.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();

        ActivityLogService::record('logout', 'Signed out of the izSuite frontend', $user, [
            'source' => 'api',
        ]);
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
        $revoked = $user->tokens()->delete();

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
        $roleId = Role::where('slug', self::FRONTEND_ROLE_SLUG)->value('id');

        if (! $roleId) {
            throw new RuntimeException(sprintf(
                'The "%s" role is missing. Run RoleSeeder before accepting registrations.',
                self::FRONTEND_ROLE_SLUG,
            ));
        }

        return $roleId;
    }

    private function tokenExpiry(bool $remember): Carbon
    {
        return $remember
            ? now()->addDays(self::REMEMBERED_TOKEN_DAYS)
            : now()->addHours(self::SESSION_TOKEN_HOURS);
    }
}
