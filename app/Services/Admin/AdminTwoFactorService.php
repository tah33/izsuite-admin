<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\User\UserRepository;
use App\Services\Api\Profile\TwoFactorService;
use App\Services\Shared\ActivityLogService;
use App\Support\Totp;
use Illuminate\Contracts\Session\Session;

/**
 * An admin's two-factor sign-in (Doc 15 section 15.2), which every admin
 * must have: set up with the authenticator app the businesses use
 * (TwoFactorService), right after the first sign-in without it. A Super
 * Admin can reset another admin's, who then sets it up again.
 */
class AdminTwoFactorService
{
    /** The name the authenticator app files an admin account under: apart from a website account of the same address. */
    public const ISSUER = TwoFactorService::ISSUER.' Admin';

    public function __construct(
        protected TwoFactorService $twoFactor,
        protected UserRepository $userRepository,
        protected AdminSessionService $sessions,
    ) {}

    public function isOn(User $user): bool
    {
        return $this->twoFactor->isOn($user);
    }

    /**
     * The secret for the app: one kept until a code confirms it, so the
     * set-up page can be opened again without scanning again.
     *
     * @return array{secret: string, uri: string}
     */
    public function pending(User $user): array
    {
        $secret = $user->two_factor_secret;

        if (! $secret) {
            $secret = Totp::secret();

            $this->userRepository->forceUpdate($user, ['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null, 'two_factor_last_step' => null]);
        }

        return ['secret' => $secret, 'uri' => Totp::uri($secret, $user->email, self::ISSUER)];
    }

    /**
     * Turns it on with the app's first code, and the panel opens.
     *
     * @return list<string> the recovery codes, shown this once
     */
    public function confirm(Session $session, User $user, string $code): array
    {
        $codes = $this->twoFactor->confirm($user, $code);

        $this->sessions->twoFactorSetUp($session);

        return $codes;
    }

    /** @return list<string> ten new recovery codes in place of the old, shown once */
    public function newRecoveryCodes(User $user, string $password): array
    {
        return $this->twoFactor->newRecoveryCodes($user, $password);
    }

    /** How many unused recovery codes $user has left. */
    public function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Turns $member's two-factor off and ends their sessions, so they set it
     * up again at their next sign-in: for a lost phone. Only a Super Admin
     * may, and not for themselves.
     */
    public function reset(User $admin, User $member): void
    {
        abort_unless($admin->isSuperAdmin(), 403, 'Only a Super Admin can reset two-factor sign-in.');
        abort_if($admin->is($member), 403, 'You cannot reset your own two-factor sign-in.');

        $this->userRepository->forceUpdate($member, [
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_last_step'      => null,
        ]);

        $this->sessions->endAll($member);

        ActivityLogService::record('updated', "Reset the two-factor sign-in of staff member \"{$member->name}\"", $member);
    }
}
