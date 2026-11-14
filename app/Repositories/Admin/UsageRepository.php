<?php

namespace App\Repositories\Admin;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceMember;
use App\Models\User\WorkspaceUsageSnapshot;
use App\Services\User\Workspaces\PlanLimits;
use App\Support\BusinessStatus;
use App\Support\UsageState;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Each business's usage against its plan for Admin > Usage (Doc 15 section
 * 15.18 - Phase D2): its users (active or invited, the owner at least),
 * active locations, storage and active apps, worked out in SQL so the list
 * can sort and filter by them - and the daily snapshots that keep their
 * history.
 */
class UsageRepository
{
    public function __construct(
        private BusinessRepository $businesses,
    ) {}

    /**
     * A page of businesses with their usage. $filters: search (name, BW- ID,
     * or the owner's name or e-mail), package (key), state (warning: nearing
     * a limit or past it; full: at one or past it; over: past one), limit
     * (users, locations or storage: the one the state looks at; else any)
     * and sort (closest, users, locations, storage, storage_bytes, newest).
     */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->businesses->search($this->withUsage(Workspace::query()), $filters['search'] ?? null);
        $query = $this->businesses->narrow($query, array_intersect_key($filters, ['package' => true]))
            ->when($filters['state'] ?? null, fn (Builder $query, string $state) => $query->whereRaw($this->inState($state, $filters['limit'] ?? null)));

        return $this->sorted($query, $filters['sort'] ?? 'closest')->paginate($perPage)->withQueryString();
    }

    /** One business with its usage, owner and country; null when there is none. */
    public function find(int $id): ?Workspace
    {
        return $this->withUsage(Workspace::query())->with(['user:id,first_name,last_name,email', 'businessSetting:id,workspace_id,country'])->find($id);
    }

    /** @return Collection<int, WorkspaceUsageSnapshot> $workspace's snapshots from $from to $to, oldest first, with their plan */
    public function history(Workspace $workspace, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return WorkspaceUsageSnapshot::with('package:id,name')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->orderBy('day')
            ->get();
    }

    /** Hands $each the businesses not closed, with their usage, a chunk at a time. */
    public function eachForSnapshot(int $size, callable $each): void
    {
        $this->withUsage(Workspace::query())
            ->where('workspaces.status', '!=', BusinessStatus::CLOSED)
            ->chunkById($size, $each, 'workspaces.id', 'id');
    }

    /** Keeps $rows (WorkspaceUsageSnapshot's columns), one a business a day: a day snapshotted again is overwritten. */
    public function saveSnapshots(array $rows): void
    {
        WorkspaceUsageSnapshot::upsert($rows, ['workspace_id', 'day'], ['package_id', 'users', 'locations', 'storage_bytes', 'apps', 'user_limit', 'location_limit', 'storage_limit_mb']);
    }

    /**
     * $query's businesses, each with its plan's name and limits and its
     * usage: users_used, locations_used, storage_bytes, apps_used and
     * plan_apps (the apps its plan includes), and - to sort by - the share
     * of each limit used (users_percent, locations_percent,
     * storage_percent; null with no limit, or a limit of one) and the
     * largest of them (top_percent). The page shows its own shares
     * (UsageService::lines()).
     */
    private function withUsage(Builder $query): Builder
    {
        $users     = $this->usersUsed();
        $locations = $this->locationsUsed();
        $storage   = $this->storageUsed();
        // The shares to sort by leave a limit of one out, as UsageState does: Billing Starter's one user is no warning.
        $percents  = [
            "case when packages.user_limit > 1 then {$users} * 100.0 / packages.user_limit end",
            "case when packages.location_limit > 1 then {$locations} * 100.0 / packages.location_limit end",
            "case when packages.storage_limit_mb > 0 then {$storage} * 100.0 / (packages.storage_limit_mb * 1048576) end",
        ];
        $largest   = DB::connection()->getDriverName() === 'sqlite' ? 'max' : 'greatest';

        return $query->select('workspaces.*')
            ->leftJoin('packages', 'packages.id', '=', 'workspaces.package_id')
            ->addSelect(['packages.name as plan_name', 'packages.user_limit as user_limit', 'packages.location_limit as location_limit', 'packages.storage_limit_mb as storage_limit_mb'])
            ->selectRaw("{$users} as users_used, {$locations} as locations_used, {$storage} as storage_bytes")
            ->selectRaw("(select count(*) from workspace_apps where workspace_apps.workspace_id = workspaces.id and workspace_apps.status = '".WorkspaceApp::STATUS_ACTIVE."') as apps_used")
            ->selectRaw('(select count(*) from package_apps where package_apps.package_id = workspaces.package_id) as plan_apps')
            ->selectRaw("{$percents[0]} as users_percent, {$percents[1]} as locations_percent, {$percents[2]} as storage_percent")
            ->selectRaw("{$largest}(coalesce({$percents[0]}, 0), coalesce({$percents[1]}, 0), coalesce({$percents[2]}, 0)) as top_percent");
    }

    /** As UsageState has it: $state for $limit (users, locations or storage), or for any of them. */
    private function inState(string $state, ?string $limit): string
    {
        $count   = fn (string $used, string $cap) => match ($state) {
            UsageState::WARNING => "({$cap} is not null and ({$used} > {$cap} or ({$cap} > 1 and {$used} * 100 >= {$cap} * ".PlanLimits::WARNING_PERCENT.')))',
            UsageState::FULL    => "({$cap} is not null and ({$used} > {$cap} or ({$cap} > 1 and {$used} >= {$cap})))",
            default             => "({$cap} is not null and {$used} > {$cap})",
        };
        $bytes   = 'packages.storage_limit_mb * 1048576';
        $storage = $this->storageUsed();
        $checks  = [
            'users'     => $count($this->usersUsed(), 'packages.user_limit'),
            'locations' => $count($this->locationsUsed(), 'packages.location_limit'),
            'storage'   => '(packages.storage_limit_mb is not null and '.match ($state) {
                UsageState::WARNING => "{$storage} * 100 >= {$bytes} * ".UsageState::storageWarning(),
                UsageState::FULL    => "{$storage} >= {$bytes}",
                default             => "{$storage} > {$bytes}",
            }.')',
        ];

        return isset($checks[$limit]) ? $checks[$limit] : '('.implode(' or ', $checks).')';
    }

    private function sorted(Builder $query, string $sort): Builder
    {
        // Shares are aliases of the select list; a business without that limit (null) comes last.
        $first = match ($sort) {
            'users'         => 'users_percent',
            'locations'     => 'locations_percent',
            'storage'       => 'storage_percent',
            'storage_bytes' => 'storage_bytes',
            'newest'        => null,
            default         => 'top_percent',
        };

        return $query->when($first, fn (Builder $query, string $column) => $query->orderByDesc($column))->orderByDesc('workspaces.id');
    }

    /** Its users, active or invited - the owner at least, as PlanLimits counts them. */
    private function usersUsed(): string
    {
        $counted = "'".implode("', '", WorkspaceMember::COUNTED)."'";
        $members = "(select count(*) from workspace_members where workspace_members.workspace_id = workspaces.id and workspace_members.status in ({$counted}))";

        return "(case when {$members} > 0 then {$members} else 1 end)";
    }

    /** Its active locations: an inactive one is outside the limit. */
    private function locationsUsed(): string
    {
        return '(select count(*) from locations where locations.workspace_id = workspaces.id and locations.is_active = 1)';
    }

    /** What its files take, in bytes. */
    private function storageUsed(): string
    {
        return '(select coalesce(sum(workspace_files.size), 0) from workspace_files where workspace_files.workspace_id = workspaces.id)';
    }
}
