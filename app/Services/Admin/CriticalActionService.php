<?php

namespace App\Services\Admin;

use App\Models\User\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A critical action (Doc 15 section 15.25) goes ahead only once the admin
 * has given their password again and a reason; the page says what it will
 * affect first. A wrong password is logged as refused, and five lock the
 * step for ten minutes. The action's own audit entries then keep the reason
 * and that the password was given again (AdminAuditLogService).
 *
 * Critical today: changing a payment method that takes real payments,
 * changing which apps every plan includes, and resetting an admin's
 * two-factor sign-in. Making a Super Admin, overriding an ownership,
 * suspending businesses in bulk and large refunds are not in the panel yet,
 * and come here when they are.
 */
class CriticalActionService
{
    /** Wrong passwords allowed in ten minutes, per admin. */
    public const MAX_TRIES = 5;

    public const MIN_REASON = 5;

    public function __construct(
        protected AdminAuditLogService $audit,
    ) {}

    /**
     * Lets $what go ahead for $admin, or refuses it with a message on the
     * password field (confirm_password) or the reason.
     */
    public function confirm(User $admin, ?string $password, ?string $reason, string $what): void
    {
        $key = 'critical-action:'.$admin->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_TRIES)) {
            throw ValidationException::withMessages(['confirm_password' => 'Too many wrong passwords. Try again in '.(int) ceil(RateLimiter::availableIn($key) / 60).' minutes.']);
        }

        $errors = [];

        if ($password === null || $password === '') {
            $errors['confirm_password'] = 'Enter your password to confirm this change.';
        } elseif (! Hash::check($password, (string) $admin->password)) {
            RateLimiter::hit($key, 600);
            $this->audit->record('refused', "Refused, wrong password: {$what}", null, null, $admin, 'refused');
            $errors['confirm_password'] = 'That password is not right.';
        }

        if (mb_strlen(trim((string) $reason)) < self::MIN_REASON) {
            $errors['reason'] = 'Give the reason for this change.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        RateLimiter::clear($key);

        $this->audit->approvedWithPassword(trim((string) $reason));
    }
}
