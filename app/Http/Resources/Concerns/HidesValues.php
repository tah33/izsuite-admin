<?php

namespace App\Http\Resources\Concerns;

use App\Services\Shared\WorkspaceAccess;

/**
 * An app's values a role may not see (Doc 12 section 14): a record's amounts,
 * prices and totals come back empty for a user without the app's Values
 * permission - the record itself still shows.
 */
trait HidesValues
{
    /**
     * $data with its fields $keys emptied while the user may not see
     * $family's values: null, or what a key maps to ('taxes' => [] keeps a
     * list a list).
     *
     * @param  array<int|string, mixed>  $keys
     */
    protected function hideValues(string $family, array $data, array $keys): array
    {
        if (app(WorkspaceAccess::class)->seesValues($family)) {
            return $data;
        }

        foreach ($keys as $key => $empty) {
            [$key, $empty] = is_int($key) ? [$empty, null] : [$key, $empty];

            if (array_key_exists($key, $data)) {
                $data[$key] = $empty;
            }
        }

        return $data;
    }
}
