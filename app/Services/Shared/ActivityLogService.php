<?php

namespace App\Services\Shared;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\ActivityLogRepository;
use App\Services\Admin\AdminAuditLogService;
use App\Support\SettingChanges;
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

    /**
     * $actor is who did it, and defaults to whoever is signed in. Pass it for
     * work done with nobody signed in - a queued job acting for an account -
     * or the entry would belong to no one.
     *
     * The entry belongs to the business it is about (Doc 11 section 24): the
     * business itself, the one a record belongs to, or the one the request is
     * for. An update records what changed - each setting's previous and new
     * value (SettingChanges) - from $subject's last save, unless $properties
     * says it already; a record with a location keeps which.
     *
     * What an admin does - or what is done to an admin's account - goes to
     * the admin panel's own audit log instead (Doc 15 section 15.26), never
     * into a business's.
     */
    public function log(string $action, string $description, ?Model $subject = null, ?array $properties = null, ?User $actor = null): ?ActivityLog
    {
        $actor ??= auth()->user();

        $changes = $action === 'updated' && $subject && ! isset($properties['changes']) ? SettingChanges::of($subject) : [];

        if ($this->isAdminActivity($actor, $subject)) {
            app(AdminAuditLogService::class)->record($action, $description, $subject, [
                ...($properties ?? []),
                ...($changes ? ['changes' => $changes] : []),
            ], $actor ?? ($subject instanceof User ? $subject : null));

            $this->markRequestAsActivityLogged();

            return null;
        }

        $properties = [
            ...($properties ?? []),
            ...($changes ? ['changes' => $changes] : []),
            ...($subject?->getAttribute('location_id') ? ['location_id' => (int) $subject->getAttribute('location_id')] : []),
        ];

        $log   = $this->repo->create([
            'user_id'      => $this->resolveActorId($actor, $subject),
            'workspace_id' => $this->resolveWorkspaceId($subject),
            'action'       => $action,
            'model_type'   => $subject ? get_class($subject) : null,
            'model_id'     => $subject?->getKey(),
            'description'  => $description,
            'properties'   => $properties ?: null,
            'ip_address'   => $this->resolveIpAddress(),
        ]);

        $this->markRequestAsActivityLogged();

        return $log;
    }

    public static function record(string $action, string $description, ?Model $subject = null, ?array $properties = null, ?User $actor = null): ?ActivityLog
    {
        return app(static::class)->log($action, $description, $subject, $properties, $actor);
    }

    /**
     * Keeps LogApiWriteActivity from writing its generic entry for this
     * request, which changes nothing worth one - or is logged later, by name,
     * once its work is done in the background (an import).
     */
    public static function skipForThisRequest(): void
    {
        app(static::class)->markRequestAsActivityLogged();
    }

    private function isAdminActivity(?User $actor, ?Model $subject): bool
    {
        if ($actor?->isAdmin()) {
            return true;
        }

        return $subject instanceof User && $subject->isAdmin();
    }

    /**
     * The business itself, the one a record belongs to, else the one the
     * request is for - or none: a business just deleted has no log to hold it.
     */
    private function resolveWorkspaceId(?Model $subject): ?int
    {
        if ($subject instanceof Workspace) {
            return $subject->exists ? $subject->getKey() : null;
        }

        if ($subject?->getAttribute('workspace_id')) {
            return (int) $subject->getAttribute('workspace_id');
        }

        $workspace = app()->bound('request') ? app(Request::class)->attributes->get('workspace') : null;

        return $workspace instanceof Workspace ? $workspace->getKey() : null;
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
