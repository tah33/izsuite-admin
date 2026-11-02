<?php

namespace App\Repositories\Shared;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\QueryFilters\ActionFilter;
use App\QueryFilters\DateRangeFilter;
use App\QueryFilters\SearchFilter;
use App\QueryFilters\UserIdFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pipeline\Pipeline;

class ActivityLogRepository
{
    /**
     * One business's activity, newest first (Settings > Security & Audit):
     * searched by what was done, narrowed to a user, a date range, or the
     * entries that recorded changes.
     */
    public function paginateForWorkspace(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(ActivityLog::with('user')->where('workspace_id', $workspace->id))
            ->through([
                new UserIdFilter($filters['user_id'] ?? null),
                new DateRangeFilter($filters['date_from'] ?? null, $filters['date_to'] ?? null),
                new SearchFilter($filters['search'] ?? null, columns: ['description']),
            ])
            ->thenReturn()
            ->when($filters['changes_only'] ?? false, fn ($query) => $query->whereNotNull('properties->changes'))
            ->latest('id')
            ->paginate($perPage);
    }

    /** Whoever appears in a business's activity, for its user filter. */
    public function usersFor(Workspace $workspace): Collection
    {
        return User::whereIn('id', ActivityLog::where('workspace_id', $workspace->id)->whereNotNull('user_id')->select('user_id'))
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get paginated activity logs with filters.
     */
    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(ActivityLog::with('user'))
            ->through([
                new UserIdFilter($filters['user_id'] ?? null),
                new ActionFilter($filters['action'] ?? null),
                new DateRangeFilter($filters['date_from'] ?? null, $filters['date_to'] ?? null),
                new SearchFilter(
                    $filters['search'] ?? null,
                    columns: ['description'],
                    relations: ['user' => ['name']],
                ),
            ])
            ->thenReturn()
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /**
     * Create a new activity log entry.
     */
    public function create(array $data): ActivityLog
    {
        return ActivityLog::create($data);
    }

    /**
     * Get distinct action types for the filter dropdown.
     */
    public function getDistinctActions(): array
    {
        return ActivityLog::distinct()->pluck('action')->sort()->values()->toArray();
    }
}
