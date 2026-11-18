<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * What an admin's password must be (Doc 15 section 15.2): at least 12
 * characters, with upper and lower case, a number and a symbol. Website
 * accounts keep Password::defaults() (8 characters, the same mix).
 */
final class AdminPassword
{
    public const MIN = 12;

    public static function rule(): Password
    {
        return Password::min(self::MIN)->mixedCase()->numbers()->symbols();
    }
}
