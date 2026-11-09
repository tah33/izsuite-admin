<?php

namespace App\Services\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * One person's save never silently overwrites another's (Doc 14 section 22
 * - Phase A). A record's version - when it was last changed, to the second -
 * goes out with it; the frontend sends it back as If-Match when it saves.
 * Changed by someone else since, the save is refused (409) with a message to
 * reload. A save without If-Match is not checked.
 */
class EditConflicts
{
    /** $model's version: when it was last changed, in UTC, to the second - as the database keeps it. */
    public static function version(Model $model): ?string
    {
        return $model->updated_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** Refuses the save when $model - "this contact" - changed since the version the request sends. */
    public static function ensureUnchanged(Model $model, string $what): void
    {
        $sent = request()->header('If-Match');

        if ($sent !== null && trim($sent, '" ') !== self::version($model)) {
            throw new HttpResponseException(response()->json([
                'message' => "Someone else changed {$what} after you opened it. Reload it to see their changes, then make yours again.",
            ], 409));
        }
    }
}
