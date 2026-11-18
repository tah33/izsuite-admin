<?php

namespace App\Services\Admin;

use App\Mail\AccountNoticeMail;
use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Api\Auth\EmailVerificationService;
use App\Services\Api\Auth\PasswordResetService;
use App\Services\Api\Profile\TwoFactorService;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * What izSuite support does to a user's account (Doc 15 section 15.7): a new
 * email verification code, a link to choose a new password, signing it out
 * everywhere, locking and unlocking it, and resetting its two-factor sign-in.
 * Nobody sees or sets a password. Each is in the audit log, with its reason.
 */
class AccountSupportService
{
    public function __construct(
        protected UserRepository $users,
        protected EmailVerificationService $verification,
        protected PasswordResetService $passwords,
        protected TwoFactorService $twoFactor,
        protected CriticalActionService $criticalActions,
    ) {}

    /** A new code to verify the account's email address, for one not verified yet. */
    public function resendVerification(User $client, User $admin): void
    {
        abort_if((bool) $client->email_verified_at, 422, 'This account\'s email address is verified already.');

        $this->verification->issue($client);

        ActivityLogService::record('verification_sent', "Sent {$client->name} a new code to verify their email address", $client, null, $admin);
    }

    /**
     * A link to choose a new password, e-mailed to the account's own address:
     * single-use, for an hour. Sent to a locked account too, so its password
     * can change before it is unlocked.
     */
    public function sendRecoveryLink(User $client, User $admin, string $reason): void
    {
        $link = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'email' => $client->email,
            'token' => $this->passwords->issueLink($client),
        ]);

        $this->tell($client, 'Choose a new password', [
            'izSuite support has sent you a link to choose a new password for your account.',
            'It works once, for the next '.PasswordResetService::LINK_TTL_MINUTES.' minutes. A new password signs you out on every device.',
            'If you did not ask for this, ignore this e-mail: your password stays as it is.',
        ], 'Choose a new password', $link);

        ActivityLogService::record('recovery_link_sent', "Sent {$client->name} a link to choose a new password", $client, ['reason' => $reason], $admin);
    }

    /** Ends every session of the account. @return int how many ended */
    public function signOutEverywhere(User $client, User $admin, string $reason): int
    {
        $ended = $this->users->revokeTokens($client);

        ActivityLogService::record('signed_out', "Signed {$client->name} out everywhere", $client, ['reason' => $reason, 'sessions' => $ended], $admin);

        return $ended;
    }

    /** Nobody signs in to the account until it is unlocked; its sessions end now. */
    public function lock(User $client, User $admin, string $reason): void
    {
        abort_if($client->status === User::LOCKED, 422, 'This account is locked already.');
        abort_if($client->status === User::CLOSED, 422, 'This account is closed, as its holder asked: there is nothing to lock.');

        $from = $client->status;

        DB::transaction(function () use ($client) {
            $this->users->forceUpdate($client, ['status' => User::LOCKED]);
            $this->users->revokeTokens($client);
        });

        ActivityLogService::record('locked', "Locked the account of {$client->name}", $client, [
            'reason'  => $reason,
            'changes' => ['status' => ['from' => $from, 'to' => User::LOCKED]],
        ], $admin);

        $this->tell($client, 'Your account is locked', [
            'izSuite support has locked your account, so nobody can sign in to it for now.',
            'Your businesses and their records are kept, and the people you work with can still use them.',
            'If you did not expect this, write to izSuite support from the Contact page.',
        ]);
    }

    public function unlock(User $client, User $admin, string $reason): void
    {
        abort_unless($client->status === User::LOCKED, 422, 'This account is not locked.');

        $this->users->forceUpdate($client, ['status' => User::ACTIVE]);

        ActivityLogService::record('unlocked', "Unlocked the account of {$client->name}", $client, [
            'reason'  => $reason,
            'changes' => ['status' => ['from' => User::LOCKED, 'to' => User::ACTIVE]],
        ], $admin);

        $this->tell($client, 'Your account is unlocked', [
            'izSuite support has unlocked your account. You can sign in again.',
        ], 'Sign in', rtrim((string) config('app.frontend_url'), '/').'/login');
    }

    /**
     * Turns the account's two-factor sign-in off and ends its sessions, once
     * support has checked who is asking: for a lost phone and lost recovery
     * codes. It turns a security control off, so it is a critical action
     * (section 15.25): the admin's password and a reason. How the person was
     * checked is kept too.
     */
    public function resetTwoFactor(User $client, User $admin, ?string $password, ?string $reason, string $identityCheck): void
    {
        abort_unless($this->twoFactor->isOn($client), 422, 'Two-factor sign-in is not on for this account.');

        $this->criticalActions->confirm($admin, $password, $reason, "reset the two-factor sign-in of {$client->name}");

        DB::transaction(function () use ($client) {
            $this->users->forceUpdate($client, [
                'two_factor_secret'         => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at'   => null,
                'two_factor_last_step'      => null,
            ]);
            $this->users->revokeTokens($client);
        });

        ActivityLogService::record('two_factor_reset', "Reset the two-factor sign-in of {$client->name}", $client, ['identity_check' => $identityCheck], $admin);

        $this->tell($client, 'Two-factor sign-in is off for your account', [
            'izSuite support has turned off two-factor sign-in for your account, after checking that you asked for it.',
            'You were signed out on every device. Sign in with your password, then turn two-factor sign-in on again in your profile.',
            'If you did not ask for this, write to izSuite support from the Contact page at once.',
        ]);
    }

    /** @param  list<string>  $lines */
    private function tell(User $client, string $title, array $lines, ?string $actionText = null, ?string $actionUrl = null): void
    {
        Mail::to($client->email)->queue(new AccountNoticeMail($client->first_name ?: $client->name, $title, $lines, $actionText, $actionUrl));
    }
}
