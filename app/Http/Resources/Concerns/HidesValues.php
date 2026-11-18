<?php

namespace App\Http\Resources\Concerns;

use App\Services\Shared\WorkspaceAccess;

/**
 * What a role may not see (Doc 12 section 14): an app's values - a record's
 * amounts, prices and totals - for a user without the app's Values
 * permission, and an item's purchase price, an account's balances or full
 * details, or tax information without their own. The record itself still
 * shows.
 */
trait HidesValues
{
    /** As hideWithout(), for $family's values. */
    protected function hideValues(string $family, array $data, array $keys): array
    {
        return $this->hideWithout("{$family}.values.view", $data, $keys);
    }

    /**
     * $data with its fields $keys emptied while the user does not hold
     * $permission: null, or what a key maps to ('taxes' => [] keeps a list
     * a list).
     *
     * @param  array<int|string, mixed>  $keys
     */
    protected function hideWithout(string $permission, array $data, array $keys): array
    {
        if (app(WorkspaceAccess::class)->can($permission)) {
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

    /**
     * $data with its fields $keys masked while the user does not hold
     * $permission: only their last four characters show - "•••• 6819" - and
     * none of a shorter one.
     *
     * @param  list<string>  $keys
     */
    protected function maskWithout(string $permission, array $data, array $keys): array
    {
        if (app(WorkspaceAccess::class)->can($permission)) {
            return $data;
        }

        foreach ($keys as $key) {
            $value = (string) ($data[$key] ?? '');

            if ($value !== '') {
                $data[$key] = mb_strlen($value) > 4 ? '•••• '.mb_substr($value, -4) : '••••';
            }
        }

        return $data;
    }
}
