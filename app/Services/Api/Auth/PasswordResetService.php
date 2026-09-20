<?php

namespace App\Services\Api\Auth;

use App\Mail\PasswordResetOtp;
use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Password reset in three steps: ask for a code, spend the code for a ticket,
 * spend the ticket for a new password.
 *
 * The middle step is what stops the last one being called on its own. Verifying
 * the code returns a single-use reset token, and only that token buys a
 * password change - so "the code was checked" is a fact the server holds, not
 * something a client asserts by having navigated to the right screen.
 *
 * Both halves live in tables the framework already ships a place for:
 * password_reset_otps holds the emailed code, password_reset_tokens holds the
 * ticket it turns into.
 */
class PasswordResetService
{
    /** Digits in the emailed code. Matches the OtpInput on the frontend. */
    public const OTP_LENGTH              = 6;

    /** Code lifetime. The mail template says "valid for 30 minutes" in words. */
    public const OTP_TTL_MINUTES         = 30;

    /** Gap enforced between two codes for one address, matching the frontend countdown. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Ticket lifetime, deliberately shorter than the code it replaces. By the
     * time it exists the visitor is already on the "choose a password" screen,
     * so it only has to cover typing a password, not reading an email.
     */
    public const RESET_TOKEN_TTL_MINUTES = 15;

    private const WRONG_CODE_MESSAGE     = 'That code is not correct. Check the email and try again.';

    private const EXPIRED_CODE_MESSAGE   = 'That code has expired. Request a new one and try again.';

    private const BAD_TOKEN_MESSAGE      = 'This password reset is no longer valid. Start again from Forgot password.';

    /**
     * Machine-readable twins of the messages above, so a client branches on
     * these and never on the wording.
     */
    private const WRONG_CODE_ACTION      = 'code_invalid';

    private const EXPIRED_CODE_ACTION    = 'code_expired';

    private const BAD_TOKEN_ACTION       = 'reset_token_invalid';

    private const NO_ACCOUNT_MESSAGE     = "We couldn't find an account with that email address.";

    private const NO_ACCOUNT_ACTION      = 'account_not_found';

    private const COOLDOWN_ACTION        = 'cooldown_active';

    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /**
     * Step one: email a code to an address that has an account.
     *
     * An admin address is answered as though it had no account here. Admins
     * sign in through the session-based panel, so a public endpoint that reset
     * an admin password would hand the panel a door it never asked for - and
     * reusing the not-found wording keeps this from being a way to pick the
     * admin accounts out of a list of addresses.
     */
    public function sendCode(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        // The exists rule has already been past, so a miss here means an admin
        // account or a row that vanished between the two queries.
        if (! $user || $user->isAdmin()) {
            $this->reject(self::NO_ACCOUNT_MESSAGE, self::NO_ACCOUNT_ACTION);
        }

        if ($remaining = $this->cooldownRemaining($email)) {
            $this->rejectCooldown($remaining);
        }

        $otp = $this->generateOtp();

        DB::transaction(function () use ($user, $otp) {
            // One live code per address: a new request retires the old one, so
            // an earlier email cannot still be spent.
            DB::table('password_reset_otps')->where('email', $user->email)->delete();

            DB::table('password_reset_otps')->insert([
                'email'      => $user->email,
                'otp'        => Hash::make($otp),
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'created_at' => now(),
            ]);
        });

        try {
            Mail::to($user->email)->send(new PasswordResetOtp($otp, self::OTP_TTL_MINUTES));
        } catch (\Throwable $e) {
            report($e);
        }

        ActivityLogService::record('updated', 'Requested a password reset code', $user, [
            'source' => 'api',
        ]);
    }

    /**
     * Step two: spend the code for a single-use reset token.
     *
     * Digits are checked before expiry on purpose. Expiry first would answer
     * "is there a stale code for this address?" to anyone typing 000000, which
     * is a way to find out who has an account; this way only someone who typed
     * the real code - so someone holding the email - is told that it expired.
     */
    public function verifyCode(string $email, string $otp): string
    {
        $record = DB::table('password_reset_otps')->where('email', $email)->first();

        if (! $record || ! Hash::check($otp, $record->otp)) {
            $this->reject(self::WRONG_CODE_MESSAGE, self::WRONG_CODE_ACTION);
        }

        if (now()->greaterThan(Carbon::parse($record->expires_at))) {
            $this->reject(self::EXPIRED_CODE_MESSAGE, self::EXPIRED_CODE_ACTION);
        }

        $resetToken = Str::random(64);

        DB::transaction(function () use ($email, $resetToken) {
            // The code is spent the moment it buys a token: it must not be able
            // to open a second reset.
            DB::table('password_reset_otps')->where('email', $email)->delete();

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($resetToken), 'created_at' => now()],
            );
        });

        return $resetToken;
    }

    /**
     * Step three: spend the token for a new password.
     *
     * Every session is revoked afterwards. A reset is what someone does when
     * they think the account is not theirs alone any more, so leaving the other
     * devices signed in would defeat the point of changing it.
     *
     * The password is all that changes. email_verified_at is deliberately left
     * alone - see the note inside.
     */
    public function resetPassword(string $email, string $resetToken, string $password): User
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($resetToken, $record->token)) {
            $this->reject(self::BAD_TOKEN_MESSAGE, self::BAD_TOKEN_ACTION);
        }

        $issuedAt = Carbon::parse($record->created_at);

        if (now()->greaterThan($issuedAt->addMinutes(self::RESET_TOKEN_TTL_MINUTES))) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            $this->reject(self::BAD_TOKEN_MESSAGE, self::BAD_TOKEN_ACTION);
        }

        $user = $this->userRepository->findByEmail($email);

        if (! $user) {
            $this->reject(self::BAD_TOKEN_MESSAGE, self::BAD_TOKEN_ACTION);
        }

        DB::transaction(function () use ($user, $password, $email) {
            // Only the password. Email verification is its own flow with its
            // own code, and a reset must not quietly stand in for it: an
            // account that has never confirmed its address still has not
            // confirmed it. An unverified account that resets here is sent
            // through /auth/email/resend before it can sign in.
            $user->forceFill(['password' => $password])->save();

            $user->tokens()->delete();

            DB::table('password_reset_tokens')->where('email', $email)->delete();
        });

        ActivityLogService::record('updated', 'Reset their password', $user, [
            'source' => 'api',
        ]);

        return $user;
    }

    /**
     * Seconds still to wait before another code may be sent, or 0 if one may
     * go now. Read from the row the last code was written on, so the cooldown
     * needs no column of its own.
     */
    private function cooldownRemaining(string $email): int
    {
        $record = DB::table('password_reset_otps')->where('email', $email)->first();

        if (! $record || blank($record->created_at)) {
            return 0;
        }

        $readyAt = Carbon::parse($record->created_at)->addSeconds(self::RESEND_COOLDOWN_SECONDS);

        return $readyAt->isFuture() ? (int) ceil(now()->diffInSeconds($readyAt, true)) : 0;
    }

    /**
     * 429 rather than a 422: nothing about the request was wrong, it was just
     * too soon. retry_after_seconds is what the screen counts down.
     */
    private function rejectCooldown(int $seconds): never
    {
        throw new HttpResponseException(response()->json([
            'message'             => "A code was just sent. Wait $seconds seconds before asking for another.",
            'action'              => self::COOLDOWN_ACTION,
            'retry_after_seconds' => $seconds,
        ], 429));
    }

    /**
     * random_int, not rand(): this is a credential, and a predictable code is
     * the same as no code. str_pad keeps leading zeros, which matters because
     * the frontend expects exactly OTP_LENGTH digits.
     */
    private function generateOtp(): string
    {
        return str_pad(
            (string) random_int(0, (10 ** self::OTP_LENGTH) - 1),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }

    /**
     * Built by hand rather than thrown as a ValidationException, which formats
     * its own body and leaves nowhere to put action. Status and shape match a
     * normal 422, so a client reading errors needs no special case.
     */
    private function reject(string $message, string $action): never
    {
        $field = match ($action) {
            self::BAD_TOKEN_ACTION  => 'reset_token',
            self::NO_ACCOUNT_ACTION => 'email',
            default                 => 'otp',
        };

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'action'  => $action,
            'errors'  => [$field => [$message]],
        ], 422));
    }
}
