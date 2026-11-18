<?php

namespace App\Support;

use App\Models\User\User;

/**
 * A user account's security alerts in Admin > Users (Doc 15 section 15.7),
 * from what ClientRepository counts on it: several failed sign-ins in a short
 * time, and a phone number another account gives too - which may be the same
 * person twice, or someone posing as them.
 */
class AccountAlerts
{
    /** Failed sign-ins - a wrong password or code - within DAYS that make an alert. */
    public const FAILED_SIGN_INS = 5;

    public const DAYS = 7;

    /** @return list<string> the alerts on $user, short enough for a list's cell, read from failed_sign_ins and shared_phone */
    public static function of(User $user): array
    {
        $failed = (int) ($user->failed_sign_ins ?? 0);

        return array_values(array_filter([
            $failed >= self::FAILED_SIGN_INS ? "{$failed} failed sign-ins" : null,
            (int) ($user->shared_phone ?? 0) > 0 ? 'Shared phone number' : null,
        ]));
    }
}
