<?php

namespace App\Services\Api\Auth;

use App\Mail\VerifyEmailOtp;
use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email activation by one-time code.
 *
 * A registration issues a code, the account stays unverified until that code
 * comes back, and AuthService::login() is what refuses to hand out a token in
 * the meantime - so nothing here has to guard the session; it only owns the
 * code itself.
 */
class EmailVerificationService
{
    /** Digits in the emailed code. The frontend's OtpInput renders this many boxes. */
    public const OTP_LENGTH              = 6;

    /** Code lifetime. The mail template says "valid for 30 minutes" in words. */
    public const OTP_TTL_MINUTES         = 30;

    /** Gap enforced between two codes for one account, matching the frontend countdown. */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /** Wrong digits, no code on file, or an address that has no account at all. */
    private const WRONG_CODE_MESSAGE     = 'That code is not correct. Check the email and try again.';

    /** Right digits, but the code is past its expiry. */
    private const EXPIRED_CODE_MESSAGE   = 'That code has expired. Request a new one and try again.';

    /**
     * Machine-readable twins of the two messages above. The frontend branches
     * on these, never on the wording - so the sentences stay free to change,
     * and to be translated, without breaking a client.
     */
    private const WRONG_CODE_ACTION      = 'code_invalid';

    private const EXPIRED_CODE_ACTION    = 'code_expired';

    private const COOLDOWN_ACTION        = 'cooldown_active';

    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /**
     * Issue a fresh code and email it.
     *
     * The mail failure is caught rather than thrown: the account has already
     * been created at this point, and losing the whole registration to a down
     * SMTP host would be worse than landing on a screen that offers "resend".
     * The exception still reaches the log, so the failure is not silent.
     */
    public function issue(User $user): void
    {
        $otp = $this->generateOtp();

        $user->forceFill([
            'verification_otp'            => Hash::make($otp),
            'verification_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ])->save();

        try {
            Mail::to($user->email)->send(new VerifyEmailOtp($otp, self::OTP_TTL_MINUTES));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Re-issue a code for an address that asked for one.
     *
     * Returns what happened so the caller can say something true: an account
     * that is already active is not an error, but telling someone a code is on
     * its way when none was sent would leave them waiting on an email that
     * never comes.
     *
     * @return 'sent'|'already_verified'
     */
    public function resend(string $email): string
    {
        $user = $this->userRepository->findByEmail($email);

        // The exists rule has already been past, so a miss means the row went
        // away between the two queries.
        if (! $user) {
            $this->reject("We couldn't find an account with that email address.", 'account_not_found');
        }

        if ($user->email_verified_at) {
            return 'already_verified';
        }

        if ($remaining = $this->cooldownRemaining($user)) {
            $this->rejectCooldown($remaining);
        }

        $this->issue($user);

        ActivityLogService::record('updated', 'Requested a new email verification code', $user, [
            'source' => 'api',
        ]);

        return 'sent';
    }

    /**
     * Check a submitted code and activate the account.
     *
     * Every failure - unknown address, no code on file, expired code, wrong
     * digits - returns the same message. Telling them apart would turn this
     * into an oracle for which addresses are registered and which codes are
     * still live.
     */
    public function verify(string $email, string $otp): User
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user && $user->email_verified_at) {
            return $user;
        }

        // Digits first, expiry second, and the order is the point. Checking
        // expiry first would answer "is there a stale code for this address?"
        // to anyone typing 000000, which is a way to find out who has an
        // account. This way only someone who typed the real code - so someone
        // holding the email - is told the code has expired.
        if (! $user || ! $this->codeOnFileMatches($user, $otp)) {
            $this->reject(self::WRONG_CODE_MESSAGE, self::WRONG_CODE_ACTION);
        }

        if ($user->verification_otp_expires_at->isPast()) {
            $this->reject(self::EXPIRED_CODE_MESSAGE, self::EXPIRED_CODE_ACTION);
        }

        $user->forceFill([
            'email_verified_at'           => now(),
            'verification_otp'            => null,
            'verification_otp_expires_at' => null,
        ])->save();

        ActivityLogService::record('updated', 'Verified their email address', $user, [
            'source' => 'api',
        ]);

        return $user;
    }

    /**
     * Do the submitted digits match the code on file? Says nothing about
     * whether that code is still in date - verify() asks that separately.
     *
     * An account with no code on file fails here rather than in its own
     * branch, so "never asked for a code" and "typed it wrong" look the same
     * from outside.
     */
    private function codeOnFileMatches(User $user, string $otp): bool
    {
        if (blank($user->verification_otp) || blank($user->verification_otp_expires_at)) {
            return false;
        }

        return Hash::check($otp, $user->verification_otp);
    }

    /**
     * Field is 'otp' either way, so the frontend keeps one place to render the
     * error; `action` rides alongside so it can do more than print the
     * sentence - put the Resend button forward on an expired code, keep focus
     * in the digit boxes on a wrong one.
     *
     * Built by hand rather than thrown as a ValidationException, which formats
     * its own body and leaves nowhere to put `action`. Status and shape are
     * kept identical, so a client already reading errors.otp needs no change.
     * Same pattern, and the same reason, as AuthService::rejectUnverifiedEmail().
     */
    private function reject(string $message, string $action): never
    {
        $field = $action === 'account_not_found' ? 'email' : 'otp';

        throw new HttpResponseException(response()->json([
            'message' => $message,
            'action'  => $action,
            'errors'  => [$field => [$message]],
        ], 422));
    }

    /**
     * Seconds still to wait before another code may be sent, or 0 if one may go
     * now. Derived from the current code's expiry, so the cooldown needs no
     * column of its own.
     */
    private function cooldownRemaining(User $user): int
    {
        if (blank($user->verification_otp_expires_at)) {
            return 0;
        }

        $readyAt = $user->verification_otp_expires_at->copy()
            ->subMinutes(self::OTP_TTL_MINUTES)
            ->addSeconds(self::RESEND_COOLDOWN_SECONDS);

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
     * the same as no code at all. str_pad keeps leading zeros, which matters
     * because the frontend expects exactly OTP_LENGTH digits.
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
}
