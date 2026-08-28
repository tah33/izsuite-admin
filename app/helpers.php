<?php

use App\Models\Admin\Currency;
use App\Models\Admin\Setting;
use App\Support\Timezone;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /**
     * Get / set application settings (cached).
     *
     * Usage:
     *   setting('site_name')                   // get value
     *   setting('site_name', 'Fallback')       // get with default
     *   setting(['site_name' => 'Acme Inc.'])  // set value(s)
     *
     * All settings are loaded from the database once and cached forever.
     * The cache is automatically cleared whenever a value is written.
     */
    function setting(string|array|null $key = null, mixed $default = null): mixed
    {
        // Bulk set: setting(['key' => 'value', ...])
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Setting::set($k, $v);
            }
            Setting::clearCache();

            return null;
        }

        // No key → return all settings as key=>value array
        if (is_null($key)) {
            return Setting::getAllCached();
        }

        // Single get
        $all = Setting::getAllCached();

        return $all[$key] ?? $default;
    }
}

if (! function_exists('format_price')) {
    /**
     * Format and optionally convert a price amount.
     *
     * Usage:
     *   format_price(19.99)          // → "$19.99" (default currency)
     *   format_price(19.99, 'EUR')   // → "€18.39" (converted via exchange rate)
     *
     * Formatting options (symbol position, separators, decimals) are read
     * from global settings. Currency data comes from the currencies table.
     */
    function format_price(float $amount, ?string $currencyCode = null): string
    {
        // Resolve which currency to use: function param -> session override -> system default
        $code = $currencyCode ?? session('admin_currency', setting('default_currency', 'USD'));

        // Read from Currency::getAllCached() rather than a per-call query.
        // IMPORTANT: do NOT cache this in a static variable — Octane workers
        // live across requests, so a static would leak and serve stale rates
        // until restart. getAllCached() is Redis-backed and invalidated on
        // every currency write (see Currency::booted()).
        $currency = Currency::getAllCached()[$code] ?? null;

        // If currency not found, fallback to raw format
        if (! $currency) {
            return $code.' '.number_format($amount, 2);
        }

        // If converting to a non-default currency, apply exchange rate
        // Base assumption: prices stored in the default currency (rate=1.0)
        $converted = $amount * $currency['exchange_rate'];

        // Read global formatting settings
        $decimals             = (int) setting('decimals', 2);
        $decimalSeparator     = setting('decimal_separator', '.');
        $thousandSeparator    = setting('thousand_separator', ',');
        $symbolPosition       = setting('symbol_position', 'left');

        // Format the number
        $formatted            = number_format($converted, $decimals, $decimalSeparator, $thousandSeparator);

        // Apply symbol position
        return $symbolPosition === 'right'
            ? $formatted.$currency['symbol']
            : $currency['symbol'].$formatted;
    }
}

if (! function_exists('slim_pagination')) {
    /**
     * Build a compact pagination payload for API responses.
     *
     * Accepts either a LengthAwarePaginator instance or a paginated array
     * (for example, the $paginated array passed to paginationInformation()).
     */
    function slim_pagination(LengthAwarePaginator|array $source): array
    {
        if ($source instanceof LengthAwarePaginator) {
            $currentPage = $source->currentPage();
            $lastPage    = $source->lastPage();

            return [
                'current_page'  => $currentPage,
                'last_page'     => $lastPage,
                'per_page'      => $source->perPage(),
                'total'         => $source->total(),
                'from'          => $source->firstItem(),
                'to'            => $source->lastItem(),
                'has_prev_page' => $currentPage > 1,
                'has_next_page' => $currentPage < $lastPage,
            ];
        }

        $currentPage = max(1, (int) ($source['current_page'] ?? 1));
        $lastPage    = max(1, (int) ($source['last_page'] ?? 1));

        return [
            'current_page'  => $currentPage,
            'last_page'     => $lastPage,
            'per_page'      => (int) ($source['per_page'] ?? 0),
            'total'         => (int) ($source['total'] ?? 0),
            'from'          => $source['from'] ?? null,
            'to'            => $source['to'] ?? null,
            'has_prev_page' => $currentPage > 1,
            'has_next_page' => $currentPage < $lastPage,
        ];
    }
}

if (! function_exists('requested_per_page')) {
    /**
     * Resolve a safe rows-per-page value from the current request.
     */
    function requested_per_page(int $default = 15, array $allowed = [10, 15, 25, 50, 100]): int
    {
        $requested = (int) request()->integer('per_page', $default);

        return in_array($requested, $allowed, true) ? $requested : $default;
    }
}

if (! function_exists('app_display_timezone')) {
    /**
     * Resolve the timezone used for response rendering.
     */
    function app_display_timezone(): string
    {
        return Timezone::resolve(
            config('app.display_timezone'),
            config('app.default_display_timezone', Timezone::UTC)
        );
    }
}

if (! function_exists('to_display_timezone')) {
    /**
     * Convert a datetime-like value from UTC into the active display timezone.
     */
    function to_display_timezone(CarbonInterface|DateTimeInterface|string|null $value, ?string $timezone = null): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $resolvedTimezone = Timezone::resolve($timezone, app_display_timezone());

        if ($value instanceof CarbonInterface) {
            return $value->copy()->setTimezone($resolvedTimezone);
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone($resolvedTimezone);
        }

        return Carbon::parse($value, Timezone::UTC)->setTimezone($resolvedTimezone);
    }
}

if (! function_exists('to_display_timezone_iso')) {
    /**
     * Convert a datetime-like value into ISO-8601 in the active display timezone.
     */
    function to_display_timezone_iso(CarbonInterface|DateTimeInterface|string|null $value, ?string $timezone = null): ?string
    {
        return to_display_timezone($value, $timezone)?->toIso8601String();
    }
}

if (! function_exists('to_display_timezone_human')) {
    /**
     * Convert a datetime-like value and render a relative string in display timezone.
     */
    function to_display_timezone_human(CarbonInterface|DateTimeInterface|string|null $value, ?string $timezone = null): ?string
    {
        return to_display_timezone($value, $timezone)?->diffForHumans();
    }
}
