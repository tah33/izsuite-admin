<?php

namespace App\Repositories\Shared;

use App\Models\Shared\ActivityLog;
use App\QueryFilters\ActionFilter;
use App\QueryFilters\DateRangeFilter;
use App\QueryFilters\SearchFilter;
use App\QueryFilters\UserIdFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;

class ActivityLogRepository
{
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
