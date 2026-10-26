<?php

namespace App\Models\Concerns;

use App\Services\User\Apps\CatalogVersion;

/**
 * For the app catalog's models: any save or delete changes the catalog
 * version, so every business sidebar is rebuilt on its next request.
 */
trait BumpsCatalogVersion
{
    protected static function bootBumpsCatalogVersion(): void
    {
        static::saved(fn () => CatalogVersion::bump());
        static::deleted(fn () => CatalogVersion::bump());
    }
}
