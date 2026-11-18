<?php

namespace App\Services\Api\Profile;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Support\RecoveryCodes;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Multi-factor authentication with an authenticator app (Doc 12 section 22),
 * the user's own on My Profile: set up with the app's secret - as a QR code
 * or typed in - and turned on once the app's first code comes back, with ten
 * one-time recovery codes for when the app is not to hand. Setting it up,
 * turning it off and making new recovery codes each need the password, so a
 * session left open cannot lock its owner out, nor let them in without the
 * app.
 *
 * Once it is on, signing in asks for a code after the password (AuthService);
 * a code, like a recovery code, works once.
 */
class TwoFactorService
{
    use RefusesConflicts;

    /** The name the authenticator app files the account under. */
    public const ISSUER = 'izSuite';

    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function isOn(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }

    /**
     * A new secret for the app, kept until a code confirms it - started again,
     * the last one is replaced.
     *
     * @return array{secret: string, uri: string}
     */
    public function start(User $user, string $password): array
    {
        $this->checkPassword($user, $password);

        if ($this->isOn($user)) {
            $this->refuse('Two-factor authentication is on already.');
        }

        $secret = Totp::secret();
        $this->userRepository->forceUpdate($user, ['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null]);

        ActivityLogService::record('updated', 'Began setting up two-factor authentication', $user, ['source' => 'api']);

        return ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email, self::ISSUER)];
    }

    /**
     * Turns it on with the app's first code.
     *
     * @return list<string> the recovery codes, shown this once
     */
    public function confirm(User $user, string $code): array
    {
        if ($this->isOn($user)) {
            $this->refuse('Two-factor authentication is on already.');
        }

        if ($user->two_factor_secret === null) {
            $this->refuse('Start the set-up again: it was not begun, or has ended.');
        }

        $step  = Totp::match($user->two_factor_secret, $code);

        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Check the time on your phone, and try the newest code.']);
        }

        $codes = RecoveryCodes::make();

        $this->userRepository->forceUpdate($user, [
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => array_map([RecoveryCodes::class, 'hash'], $codes),
            'two_factor_last_step'      => $step,
        ]);

        ActivityLogService::record('updated', 'Turned on two-factor authentication', $user, ['source' => 'api']);

        return $codes;
    }

    public function disable(User $user, string $password): void
    {
        $this->checkPassword($user, $password);

        $this->userRepository->forceUpdate($user, [
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_last_step'      => null,
        ]);

        ActivityLogService::record('updated', 'Turned off two-factor authentication', $user, ['source' => 'api']);
    }

    /**
     * Ten new recovery codes in the place of every one before.
     *
     * @return list<string> shown this once
     */
    public function newRecoveryCodes(User $user, string $password): array
    {
        $this->checkPassword($user, $password);

        if (! $this->isOn($user)) {
            $this->refuse('Turn on two-factor authentication first.');
        }

        $codes = RecoveryCodes::make();
        $this->userRepository->forceUpdate($user, ['two_factor_recovery_codes' => array_map([RecoveryCodes::class, 'hash'], $codes)]);

        ActivityLogService::record('updated', 'Made new recovery codes', $user, ['source' => 'api']);

        return $codes;
    }

    /**
     * Whether $code - from the app, or a recovery code - signs $user in. Each
     * works once: a code's step is spent, a recovery code is struck off.
     *
     * @return 'code'|'recovery_code'|null what signed in, or null
     */
    public function passes(User $user, ?string $code, ?string $recoveryCode): ?string
    {
        if (! $this->isOn($user) || $user->two_factor_secret === null) {
            return null;
        }

        if ($code !== null && $code !== '') {
            $step = Totp::match($user->two_factor_secret, $code);

            if ($step === null || $step <= (int) $user->two_factor_last_step) {
                return null;
            }

            $this->userRepository->forceUpdate($user, ['two_factor_last_step' => $step]);

            return 'code';
        }

        $hashes = $user->two_factor_recovery_codes ?? [];
        $hash   = RecoveryCodes::hash((string) $recoveryCode);

        if ($recoveryCode === null || ! in_array($hash, $hashes, true)) {
            return null;
        }

        $this->userRepository->forceUpdate($user, ['two_factor_recovery_codes' => array_values(array_diff($hashes, [$hash]))]);

        return 'recovery_code';
    }

    private function checkPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }
    }
}
