<?php

namespace App\Services\Admin;

use App\Models\Admin\AdminAuditLog;
use App\Models\User\User;
use App\Repositories\Admin\AdminAuditLogRepository;
use App\Support\Csv;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin panel's audit log (Doc 15 section 15.26), apart from the
 * businesses' Activity Logs: what each admin did or tried, read in Admin >
 * Audit Log and exported by roles allowed to.
 *
 * ActivityLogService sends here whatever an admin does, so the admin
 * services log as before. A critical action's reason and approval
 * (CriticalActionService) go on every entry the rest of its request writes.
 */
class AdminAuditLogService
{
    private const REASON   = 'admin_audit.reason';

    private const APPROVAL = 'admin_audit.approval';

    public function __construct(
        protected AdminAuditLogRepository $repository,
    ) {}

    /**
     * Keeps one entry. $properties may hold `changes` (each value's previous
     * and new value) and `reason`; the rest is kept as it is. $actor is the
     * admin, by default whoever is signed in.
     */
    public function record(string $action, string $description, ?Model $subject = null, ?array $properties = null, ?User $actor = null, string $result = 'succeeded'): AdminAuditLog
    {
        $actor      ??= auth()->user();
        $properties ??= [];
        $request      = app()->bound('request') ? app(Request::class) : null;
        $changes      = $properties['changes'] ?? null;
        $reason       = $properties['reason'] ?? $request?->attributes->get(self::REASON);

        unset($properties['changes'], $properties['reason']);

        return $this->repository->create([
            'user_id'     => $actor?->id,
            'admin_name'  => $actor?->name,
            'role_name'   => $actor ? ($actor->isSuperAdmin() ? 'Super Admin' : $actor->role?->name) : null,
            'action'      => Str::limit($action, 30, ''),
            'description' => Str::limit($description, 500),
            'model_type'  => $subject ? $subject::class : null,
            'model_id'    => $subject?->getKey(),
            'changes'     => $changes ?: null,
            'reason'      => $reason,
            'approval'    => $request?->attributes->get(self::APPROVAL),
            'result'      => $result,
            'properties'  => $properties ?: null,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'session'     => $request?->hasSession() ? substr(hash('sha256', $request->session()->getId()), 0, 16) : null,
        ]);
    }

    /**
     * The rest of this request's entries keep $reason - for a change that
     * needs one but not the password (Doc 15 section 15.13: every manual
     * subscription change), made through services that log on their own.
     */
    public function withReason(string $reason): void
    {
        if (app()->bound('request')) {
            app(Request::class)->attributes->add([self::REASON => $reason]);
        }
    }

    /** The rest of this request's entries keep $reason, and that the admin's password was given again. */
    public function approvedWithPassword(string $reason): void
    {
        if (app()->bound('request')) {
            app(Request::class)->attributes->add([self::REASON => $reason, self::APPROVAL => 'password']);
        }
    }

    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->repository->page($filters, $perPage);
    }

    public function find(int $id): AdminAuditLog
    {
        return $this->repository->find($id) ?? abort(404);
    }

    /** What the filters offer: the admins, actions and kinds of record the log holds. */
    public function filterOptions(): array
    {
        return [
            'admins'      => $this->repository->admins(),
            'actions'     => $this->repository->actions(),
            'recordTypes' => $this->repository->recordTypes(),
            'results'     => AdminAuditLog::RESULTS,
        ];
    }

    /** The entries $filters picks, as a CSV file. The export is itself logged. */
    public function export(array $filters): StreamedResponse
    {
        $count = $this->repository->count($filters);

        $this->record('exported', "Exported {$count} audit ".Str::plural('entry', $count), null, ['filters' => array_filter($filters)]);

        $rows = $this->repository->cursor($filters)->map(fn (AdminAuditLog $entry) => [
            $entry->created_at->toIso8601String(), $entry->admin_name, $entry->role_name, $entry->action, $entry->description,
            $entry->model_type ? class_basename($entry->model_type) : null, $entry->model_id, AdminAuditLog::RESULTS[$entry->result] ?? $entry->result,
            $entry->reason, $entry->approval, $entry->ip_address, $entry->browser(), $entry->changes ? json_encode($entry->changes) : null,
        ]);

        return Csv::download('audit-log-'.now()->toDateString().'.csv', [
            'Time', 'Admin', 'Role', 'Action', 'Description', 'Record', 'Record ID', 'Result', 'Reason', 'Approval', 'IP Address', 'Browser', 'Changes',
        ], $rows);
    }
}
