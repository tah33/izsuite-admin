<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What a save changed, for the Activity Log (Doc 11 section 24): each setting
 * that differs, with its previous and new value. Sensitive values - account
 * and card numbers - show only their last four characters; long texts are
 * cut short; bookkeeping columns and secrets are left out.
 */
final class SettingChanges
{
    /** Never shown whole. */
    private const MASKED = ['account_number', 'iban', 'swift_code', 'routing_number', 'merchant_number', 'card_number'];

    /**
     * Bookkeeping, not settings - and secrets, which no log keeps, not even
     * hashed: a password, a sign-in code, an authenticator app's secret and
     * the recovery codes.
     */
    private const IGNORED = [
        'created_at', 'updated_at', 'created_by', 'updated_by', 'nav_version',
        'password', 'remember_token', 'verification_otp', 'verification_otp_expires_at',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_step',
    ];

    private const LONGEST = 200;

    /**
     * Each key of $after whose value differs from $before's.
     *
     * @return list<array{setting: string, from: string|bool|null, to: string|bool|null}>
     */
    public static function between(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $setting => $to) {
            $from = $before[$setting] ?? null;

            if (in_array($setting, self::IGNORED, true) || self::normal($from) === self::normal($to)) {
                continue;
            }

            $changes[] = ['setting' => $setting, 'from' => self::shown($setting, $from), 'to' => self::shown($setting, $to)];
        }

        return $changes;
    }

    /** What $model's last save changed, each side read through the model's casts. */
    public static function of(Model $model): array
    {
        $previous = $model->newInstance()->setRawAttributes($model->getPrevious());
        $keys     = array_keys($model->getChanges());

        return self::between(
            array_combine($keys, array_map(fn (string $key) => $previous->getAttribute($key), $keys)) ?: [],
            array_combine($keys, array_map(fn (string $key) => $model->getAttribute($key), $keys)) ?: [],
        );
    }

    /** One form for any value, to tell whether two differ. */
    private static function normal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $value instanceof \BackedEnum        => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value)                      => $value ? 'true' : 'false',
            is_array($value)                     => json_encode($value),
            default                              => (string) $value,
        };
    }

    /** A value as the log keeps it: lists joined, yes and no as booleans, sensitive ones masked. */
    private static function shown(string $setting, mixed $value): string|bool|null
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item), $value));
        }

        $text = self::normal($value);

        if ($text === null) {
            return null;
        }

        return in_array($setting, self::MASKED, true) ? '••••'.mb_substr($text, -4) : Str::limit($text, self::LONGEST);
    }
}
