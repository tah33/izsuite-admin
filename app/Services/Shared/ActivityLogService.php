<?php

namespace App\Services\Shared;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Repositories\Shared\ActivityLogRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function __construct(
        protected ActivityLogRepository $repo,
    ) {}

    public function getLogs(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->repo->getPaginated($filters, $perPage);
    }

    public function getDistinctActions(): array
    {
        return $this->repo->getDistinctActions();
    }

    public function log(string $action, string $description, ?Model $subject = null, ?array $properties = null): ?ActivityLog
    {
        $actor = auth()->user();

        if ($this->shouldSkipAdminActivity($actor, $subject)) {
            return null;
        }

        $log   = $this->repo->create([
            'user_id'     => $this->resolveActorId($actor, $subject),
            'action'      => $action,
            'model_type'  => $subject ? get_class($subject) : null,
            'model_id'    => $subject?->getKey(),
            'description' => $description,
            'properties'  => $properties,
            'ip_address'  => $this->resolveIpAddress(),
        ]);

        $this->markRequestAsActivityLogged();

        return $log;
    }

    public static function record(string $action, string $description, ?Model $subject = null, ?array $properties = null): ?ActivityLog
    {
        return app(static::class)->log($action, $description, $subject, $properties);
    }

    private function shouldSkipAdminActivity(?User $actor, ?Model $subject): bool
    {
        if ($actor?->isAdmin()) {
            return true;
        }

        return $subject instanceof User && $subject->isAdmin();
    }

    private function resolveActorId(?User $actor, ?Model $subject): ?int
    {
        if ($actor) {
            return $actor->id;
        }

        return $subject instanceof User ? $subject->id : null;
    }

    private function markRequestAsActivityLogged(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        app(Request::class)->attributes->set('activity_log_written', true);
    }

    private function resolveIpAddress(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app(Request::class)->ip();
    }
}
