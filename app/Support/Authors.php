<?php

namespace App\Support;

use Closure;

/**
 * Who is doing the work now, for the records that keep who added and last
 * changed them (Doc 14 section 14.5 - see RecordsAuthors). That is the
 * signed-in user, or whoever as() names for work done with nobody signed
 * in: an import's rows, added by a queued job for the user who started it.
 */
final class Authors
{
    private static ?int $acting = null;

    public static function current(): ?int
    {
        return self::$acting ?? auth()->id();
    }

    /** $work done as user $id. */
    public static function as(?int $id, Closure $work): mixed
    {
        $before       = self::$acting;
        self::$acting = $id;

        try {
            return $work();
        } finally {
            self::$acting = $before;
        }
    }
}
