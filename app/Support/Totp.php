<?php

namespace App\Support;

/**
 * Time-based one-time passwords (RFC 6238), as authenticator apps make them
 * (Doc 12 section 22): six digits from an HMAC-SHA1 of the 30-second step
 * since 1970, keyed with a shared secret written in base32 (RFC 4648).
 *
 * A code is taken for the step before and after the present one too, so a
 * phone whose clock is a little off still signs in.
 */
final class Totp
{
    public const DIGITS    = 6;

    public const PERIOD    = 30;

    /** Steps either side of the present one a code is still taken for. */
    public const WINDOW    = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new secret: 160 random bits, in base32 - 32 characters. */
    public static function secret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** The step of $time (now by default - the app's now, which tests can move): which 30 seconds it falls in. */
    public static function step(?int $time = null): int
    {
        return intdiv($time ?? now()->getTimestamp(), self::PERIOD);
    }

    /** The code for $step: the HOTP (RFC 4226) of its counter. */
    public static function code(string $secret, int $step): string
    {
        $hash   = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value  = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** The step near $time that $code was made for - null when it is none of them. */
    public static function match(string $secret, string $code, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $now  = self::step($time);

        for ($step = $now - self::WINDOW; $step <= $now + self::WINDOW; $step++) {
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** What the QR code holds: the otpauth:// address an authenticator app reads. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account).'?'.http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $text = '';

        foreach (str_split($bits, 5) as $chunk) {
            $text .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $text;
    }

    private static function base32Decode(string $text): string
    {
        $bits  = '';

        foreach (str_split(strtoupper(preg_replace('/[\s=]+/', '', $text) ?? '')) as $character) {
            $value = strpos(self::ALPHABET, $character);

            if ($value !== false) {
                $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
            }
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
