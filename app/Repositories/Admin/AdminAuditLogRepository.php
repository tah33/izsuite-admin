<?php

namespace App\Repositories\Admin;

use App\Models\Admin\AdminAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/** The admin panel's audit log (Doc 15 section 15.26): written once, read and searched. */
class AdminAuditLogRepository
{
    public function create(array $data): AdminAuditLog
    {
        return AdminAuditLog::create($data);
    }

    public function find(int $id): ?AdminAuditLog
    {
        return AdminAuditLog::find($id);
    }

    /** A page of the entries $filters picks, newest first. */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)->latest('id')->paginate($perPage)->withQueryString();
    }

    public function count(array $filters): int
    {
        return $this->filtered($filters)->count();
    }

    /** Every entry $filters picks, newest first, read a few at a time. */
    public function cursor(array $filters): LazyCollection
    {
        return $this->filtered($filters)->latest('id')->cursor();
    }

    /** @return Collection<int, string> the admins with entries, by id: as each was last named */
    public function admins(): Collection
    {
        return AdminAuditLog::whereNotNull('user_id')
            ->whereIn('id', AdminAuditLog::selectRaw('max(id)')->whereNotNull('user_id')->groupBy('user_id'))
            ->orderBy('admin_name')
            ->pluck('admin_name', 'user_id');
    }

    /** @return list<string> */
    public function actions(): array
    {
        return AdminAuditLog::distinct()->orderBy('action')->pluck('action')->all();
    }

    /** @return list<string> the kinds of record entries name */
    public function recordTypes(): array
    {
        return AdminAuditLog::whereNotNull('model_type')->distinct()->orderBy('model_type')->pluck('model_type')->all();
    }

    /**
     * $filters: user_id, action, model_type, result, from and to (dates, both
     * days in), and search (in the description, or a record's id).
     */
    private function filtered(array $filters): Builder
    {
        return AdminAuditLog::query()
            ->when($filters['user_id'] ?? null, fn (Builder $query, $id) => $query->where('user_id', $id))
            ->when($filters['action'] ?? null, fn (Builder $query, $action) => $query->where('action', $action))
            ->when($filters['model_type'] ?? null, fn (Builder $query, $type) => $query->where('model_type', $type))
            ->when($filters['result'] ?? null, fn (Builder $query, $result) => $query->where('result', $result))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('description', 'like', '%'.addcslashes($search, '%_\\').'%');

                    if (ctype_digit($search)) {
                        $query->orWhere('model_id', (int) $search);
                    }
                });
            });
    }
}
