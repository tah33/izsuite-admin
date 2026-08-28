<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Currency extends Model
{
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'exchange_rate',
        'is_default',
        'is_active',
    ];

    protected $casts    = [
        'exchange_rate' => 'decimal:6',
        'is_default'    => 'boolean',
        'is_active'     => 'boolean',
    ];

    /**
     * Cache key for the code => [symbol, exchange_rate] lookup used by
     * format_price(). Only the fields price formatting needs are cached, not
     * whole models, so this stays small.
     */
    private const CACHE_KEY = 'currencies';

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // ── Cache ──

    /**
     * Get active currencies as code => [symbol, exchange_rate], cached forever.
     *
     * Octane-safe: the value lives in the shared cache store (Redis in
     * production), never in a per-worker static, so a rate change is visible
     * to every worker once the cache is cleared — and there is nothing for
     * the long-lived worker to leak between requests.
     */
    public static function getAllCached(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, function () {
            return static::active()
                ->get()
                ->mapWithKeys(fn (self $c) => [
                    $c->code => [
                        'symbol'       => $c->symbol,
                        'exchange_rate' => (float) $c->exchange_rate,
                    ],
                ])
                ->toArray();
        });
    }

    /**
     * Clear the currency cache. Called on model save/delete below.
     */
    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    protected static function booted(): void
    {
        // Any write (admin editing a rate, toggling active, etc.) invalidates
        // the cache so the next read picks up the new value.
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
