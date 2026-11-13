<?php

namespace App\Support;

use App\Models\User\Workspace;
use Illuminate\Support\Str;

/**
 * A business's users, active locations and storage against its plan's
 * limits, for Admin > Businesses (Doc 15 section 15.5). Reached is at a
 * limit above one, or past any - as the list's filter has it, so a Billing
 * Starter business at its one user is not flagged. Expects the counts
 * BusinessRepository loads: users_count, locations_count and storage_bytes.
 */
final class BusinessUsage
{
    /** @return list<array{label: string, reached: bool}> */
    public static function of(Workspace $workspace): array
    {
        $plan  = $workspace->package;
        $bytes = (int) ($workspace->storage_bytes ?? 0);
        $limit = $plan?->storage_limit_mb ? $plan->storage_limit_mb * 1048576 : null;

        return [
            self::counted((int) ($workspace->users_count ?? 0), $plan?->user_limit, 'user'),
            self::counted((int) ($workspace->locations_count ?? 0), $plan?->location_limit, 'location'),
            ['label' => Bytes::format($bytes).($limit ? ' / '.Bytes::format($limit) : '').' storage', 'reached' => $limit !== null && $bytes >= $limit],
        ];
    }

    /** @return array{label: string, reached: bool} "3 / 5 users" */
    private static function counted(int $count, ?int $limit, string $noun): array
    {
        return [
            'label'   => $count.($limit !== null ? " / {$limit}" : '').' '.Str::plural($noun, $limit ?? $count),
            'reached' => $limit !== null && ($count > $limit || ($limit > 1 && $count >= $limit)),
        ];
    }
}
