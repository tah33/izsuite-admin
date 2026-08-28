<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable     = [
        'group',
        'key',
        'value',
    ];

    /**
     * Cache key for all settings.
     */
    private const CACHE_KEY = 'app_settings';

    /**
     * Get all settings as a key => value array (cached forever).
     */
    public static function getAllCached(): array
    {
        return Cache::rememberForever(static::CACHE_KEY, function () {
            return static::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Clear the settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::getAllCached();

        return $all[$key] ?? $default;
    }

    /**
     * Set a setting value by key (writes to DB + clears cache).
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );

        static::clearCache();

        return $setting;
    }
}
