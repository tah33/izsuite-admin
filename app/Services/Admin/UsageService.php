<?php

namespace App\Services\Admin;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceUsageSnapshot;
use App\Repositories\Admin\UsageRepository;
use App\Repositories\Billing\PackageRepository;
use App\Support\UsageState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Admin > Usage (Doc 15 section 15.18 - Phase D2): each business's users,
 * active locations, storage and apps against its plan's limits, how much of
 * each it uses and where it stands; and a business's history, from the
 * snapshot usage:snapshot keeps each day. Nothing here removes a business's
 * data: its limits only stop it adding more (Doc 13).
 */
class UsageService
{
    /** Days a business's history covers when none are chosen: the last 90, today included. */
    public const HISTORY_DAYS = 90;

    public const STATES = [
        UsageState::WARNING => 'Nearing a limit or past it',
        UsageState::FULL    => 'At a limit or past it',
        UsageState::OVER    => 'Past a limit',
    ];

    public const LIMITS = ['users' => 'Users', 'locations' => 'Locations', 'storage' => 'Storage'];

    public const SORTS = [
        'closest'       => 'Closest to a limit',
        'users'         => 'Share of users',
        'locations'     => 'Share of locations',
        'storage'       => 'Share of storage',
        'storage_bytes' => 'Most storage',
        'newest'        => 'Newest',
    ];

    public function __construct(
        protected UsageRepository $usage,
        protected PackageRepository $packages,
    ) {}

    /** The list for $filters - each business with its usage - and what the filters offer. */
    public function page(array $filters): array
    {
        return [
            'rows'  => $this->usage->page($filters, requested_per_page())->through(fn (Workspace $business) => ['business' => $business, 'usage' => self::lines($business)]),
            'plans' => $this->packages->allByRank()->pluck('name', 'key'),
        ];
    }

    /** A business's usage now, and its history from $filters' days (the last HISTORY_DAYS unless chosen). */
    public function details(int $id, array $filters): array
    {
        $business = $this->usage->find($id) ?? abort(404);
        $to       = CarbonImmutable::parse($filters['to'] ?? today());
        $from     = isset($filters['from']) ? CarbonImmutable::parse($filters['from']) : $to->subDays(self::HISTORY_DAYS - 1);
        $history  = $this->usage->history($business, $from, $to);

        return [
            'business' => $business,
            'usage'    => self::lines($business),
            'filters'  => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'chart'    => [
                'labels'    => $history->map(fn (WorkspaceUsageSnapshot $day) => $day->day->format('M j'))->all(),
                'users'     => $history->pluck('users')->all(),
                'locations' => $history->pluck('locations')->all(),
                'apps'      => $history->pluck('apps')->all(),
                'storage'   => $history->map(fn (WorkspaceUsageSnapshot $day) => round($day->storage_bytes / 1048576, 2))->all(),
            ],
            'changes'  => $this->changes($history),
        ];
    }

    /**
     * Keeps each business's usage today, in UTC - a business closed left
     * out - overwriting a snapshot already kept for the day. How many it kept.
     */
    public function snapshot(): int
    {
        $day   = CarbonImmutable::now('UTC')->toDateString();
        $count = 0;

        $this->usage->eachForSnapshot(500, function ($businesses) use ($day, &$count) {
            $this->usage->saveSnapshots($businesses->map(fn (Workspace $business) => [
                'workspace_id'     => $business->id,
                'day'              => $day,
                'package_id'       => $business->package_id,
                'users'            => (int) $business->users_used,
                'locations'        => (int) $business->locations_used,
                'storage_bytes'    => (int) $business->storage_bytes,
                'apps'             => (int) $business->apps_used,
                'user_limit'       => $business->user_limit,
                'location_limit'   => $business->location_limit,
                'storage_limit_mb' => $business->storage_limit_mb,
            ])->all());

            $count += $businesses->count();
        });

        return $count;
    }

    /**
     * $business's usage as UsageRepository loads it: each limit used, its
     * limit (null: none), the share used and its state; its apps on, and how
     * many its plan includes; and the worst state of the three.
     *
     * @return array{users: array, locations: array, storage: array, apps: array{used: int, plan: int}, state: string}
     */
    public static function lines(Workspace $business): array
    {
        $count = fn (int $used, $limit) => [
            'used'    => $used,
            'limit'   => $limit === null ? null : (int) $limit,
            'percent' => UsageState::percent($used, $limit === null ? null : (int) $limit),
            'state'   => UsageState::count($used, $limit === null ? null : (int) $limit),
        ];
        $bytes = (int) $business->storage_bytes;
        $limit = $business->storage_limit_mb === null ? null : (int) $business->storage_limit_mb * 1048576;
        $lines = [
            'users'     => $count((int) $business->users_used, $business->user_limit),
            'locations' => $count((int) $business->locations_used, $business->location_limit),
            'storage'   => ['used' => $bytes, 'limit' => $limit, 'percent' => UsageState::percent($bytes, $limit), 'state' => UsageState::storage($bytes, $limit)],
            'apps'      => ['used' => (int) $business->apps_used, 'plan' => (int) $business->plan_apps],
        ];

        return [...$lines, 'state' => UsageState::worst($lines['users']['state'], $lines['locations']['state'], $lines['storage']['state'])];
    }

    /** @return Collection<int, WorkspaceUsageSnapshot> the days its plan or usage changed, newest first - the first day always */
    private function changes(Collection $history): Collection
    {
        $before = null;

        return $history->filter(function (WorkspaceUsageSnapshot $day) use (&$before) {
            $now     = [$day->package_id, $day->users, $day->locations, $day->storage_bytes, $day->apps, $day->user_limit, $day->location_limit, $day->storage_limit_mb];
            $changed = $now !== $before;
            $before  = $now;

            return $changed;
        })->reverse()->values();
    }
}
