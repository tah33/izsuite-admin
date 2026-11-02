<?php

namespace App\Services\Api\Security;

use App\Models\Shared\ActivityLog;
use App\Models\User\Workspace;
use App\Repositories\Shared\ActivityLogRepository;
use App\Repositories\User\LocationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Settings > Security & Audit > Activity Log (Doc 11 section 24, Doc 07
 * section 23, Doc 14 section 14.24): what was done in the business, by whom
 * and when - records made and changed, settings with their previous and new
 * values, payments, exports, activations. Only the business's own entries,
 * newest first; the location an entry is about, by name, where it has one.
 * Entries are written as things happen (ActivityLogService), never here.
 */
class AuditLogService
{
    public function __construct(
        protected ActivityLogRepository $activityLogRepository,
        protected LocationRepository $locationRepository,
    ) {}

    /** $filters is validated (see ListActivityLogRequest). */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        $logs = $this->activityLogRepository->paginateForWorkspace($workspace, [
            ...$filters,
            'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
        ], $perPage);

        $ids   = $logs->getCollection()->map(fn (ActivityLog $log) => $log->properties['location_id'] ?? null)->filter()->unique()->values()->all();
        $names = $this->locationRepository->namesFor($workspace, $ids);

        $logs->getCollection()->each(function (ActivityLog $log) use ($names) {
            $log->location_name = $names[$log->properties['location_id'] ?? 0] ?? null;
        });

        return $logs;
    }

    /** Everyone who appears in the business's activity, for its user filter. */
    public function users(Workspace $workspace): Collection
    {
        return $this->activityLogRepository->usersFor($workspace);
    }
}
