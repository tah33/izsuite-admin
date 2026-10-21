<?php

namespace App\Services\User\Apps;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A value that changes whenever the admin panel changes the app catalog -
 * categories, apps, dependencies, plan access, menu items.
 *
 * Every business sidebar is cached under a key that includes it, so one change
 * here makes every business build its sidebar afresh on its next request,
 * without clearing anything by hand.
 */
final class CatalogVersion
{
    private const KEY = 'catalog_version';

    public static function current(): string
    {
        return (string) Cache::get(self::KEY, 'v0');
    }

    public static function bump(): void
    {
        Cache::forever(self::KEY, Str::random(10));
    }
}
