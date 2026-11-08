<?php

namespace App\Support;

/**
 * Recovery codes for multi-factor authentication (Doc 12 section 22): one-time
 * codes that sign in when the authenticator app is not to hand. Ten at a time,
 * "ABCDE-23456", from letters and digits that cannot be mistaken for one
 * another. Only their one-way hashes are kept, so each is shown once.
 */
final class RecoveryCodes
{
    public const COUNT     = 10;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @return list<string> */
    public static function make(): array
    {
        return array_map(fn () => self::part().'-'.self::part(), range(1, self::COUNT));
    }

    /** The one-way hash a code is kept as - the same however it is typed. */
    public static function hash(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
    }

    private static function part(): string
    {
        $part = '';

        for ($i = 0; $i < 5; $i++) {
            $part .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $part;
    }
}
