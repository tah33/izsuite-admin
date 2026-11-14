<?php

namespace App\Repositories\Admin;

use App\Models\Admin\AdminAuditLog;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceMember;
use App\QueryFilters\SearchFilter;
use App\Services\Api\Auth\AuthService;
use App\Support\AccountAlerts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pipeline\Pipeline;

/**
 * The platform's clients: the accounts that signed up on the website, each
 * with the businesses (workspaces) it owns, its places in businesses, and
 * what makes its security alerts (Doc 15 section 15.7).
 */
class ClientRepository
{
    /** The role every self-registered account gets (see AuthService): the people Admin > Users lists, and Admin > Overview counts. */
    public const ROLE = 'user';

    /**
     * Newest first, each with the businesses it owns and their plans, how
     * many businesses it is in, and its alerts' counts. $filters: search
     * (name, e-mail, phone or a business's name), package (a plan key one of
     * its businesses is on), status, verified (yes, no), two_factor (on,
     * off) and alerts (yes).
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send($this->withAlerts($this->clients()->select(['users.id', 'first_name', 'last_name', 'email', 'phone', 'status', 'email_verified_at', 'two_factor_confirmed_at', 'last_login_at', 'users.created_at']))
                ->with(['workspaces' => fn ($q) => $q->select(['id', 'user_id', 'name', 'package_id'])->orderBy('id')->with('package:id,name')])
                ->withCount(['memberships as businesses_count' => fn ($q) => $q->where('status', WorkspaceMember::ACTIVE)])
                ->when($filters['package'] ?? null, fn ($q, $package) => $q->whereHas('workspaces.package', fn ($p) => $p->where('key', $package)))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->when($filters['verified'] ?? null, fn ($q, $verified) => $verified === 'yes' ? $q->whereNotNull('email_verified_at') : $q->whereNull('email_verified_at'))
                ->when($filters['two_factor'] ?? null, fn ($q, $on) => $on === 'on' ? $q->whereNotNull('two_factor_confirmed_at') : $q->whereNull('two_factor_confirmed_at'))
                ->when(($filters['alerts'] ?? null) === 'yes', fn ($q) => $q->where(fn ($q) => $q
                    ->where(fn (QueryBuilder $sub) => $this->failedSignIns($sub), '>=', AccountAlerts::FAILED_SIGN_INS)
                    ->orWhereExists(fn (QueryBuilder $sub) => $this->samePhone($sub->selectRaw('1'))))))
            ->through([
                new SearchFilter($filters['search'] ?? '', columns: ['first_name', 'last_name', 'email', 'phone'], relations: ['workspaces' => ['name']]),
            ])
            ->thenReturn()
            ->latest('users.id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** Just a client's account; anyone else is a 404. */
    public function findOrFail(int $id): User
    {
        return $this->clients()->findOrFail($id);
    }

    /**
     * A client with each business they own: its plan and storage limit, the
     * apps it has on, how many users (active or invited - users_count),
     * locations, contacts and items it holds, and what its files take up
     * (storage_bytes). Also the businesses they are in, with their role there,
     * their sessions still open, and their alerts' counts. Anyone else is a
     * 404.
     */
    public function findWithBusinessesOrFail(int $id): User
    {
        return $this->withAlerts($this->clients()->select(['users.id', 'first_name', 'last_name', 'username', 'email', 'phone', 'status', 'timezone', 'currency', 'email_verified_at', 'two_factor_confirmed_at', 'last_login_at', 'users.created_at']))
            ->withCount(['tokens as sessions_count' => fn ($q) => $q->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))])
            ->with([
                'workspaces' => fn ($q) => $q
                    ->select(['id', 'user_id', 'name', 'package_id', 'created_at'])
                    ->withCount([
                        'members as users_count' => fn ($q) => $q->whereIn('status', WorkspaceMember::COUNTED),
                        'locations', 'contacts', 'items',
                    ])
                    ->withSum('businessFiles as storage_bytes', 'size')
                    ->orderBy('id')
                    ->with([
                        'package:id,name,storage_limit_mb',
                        'workspaceApps' => fn ($q) => $q->where('status', WorkspaceApp::STATUS_ACTIVE)->with('app:id,name,edition,sort_order'),
                    ]),
                'memberships' => fn ($q) => $q
                    ->select(['id', 'workspace_id', 'user_id', 'role_id', 'status', 'created_at'])
                    ->where('status', '!=', WorkspaceMember::REMOVED)
                    ->orderBy('id')
                    ->with(['workspace:id,user_id,name,status', 'role:id,name']),
            ])
            ->findOrFail($id);
    }

    /** @return Collection<int, ActivityLog> the account's failed sign-ins in the alert's days, newest first */
    public function recentFailedSignIns(User $client, int $limit = 5): Collection
    {
        return ActivityLog::query()
            ->where('model_type', User::class)
            ->where('model_id', $client->id)
            ->where('action', AuthService::FAILED_SIGN_IN)
            ->where('created_at', '>=', now()->subDays(AccountAlerts::DAYS))
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'description', 'ip_address', 'created_at']);
    }

    /** @return Collection<int, User> the other clients that give $client's phone number */
    public function sharingPhone(User $client): Collection
    {
        if (blank($client->phone)) {
            return new Collection;
        }

        return $this->clients()
            ->where('phone', $client->phone)
            ->where('users.id', '!=', $client->id)
            ->orderBy('users.id')
            ->get(['users.id', 'first_name', 'last_name', 'email', 'status', 'created_at']);
    }

    /** @return Collection<int, AdminAuditLog> the latest admin entries about the account */
    public function adminActivity(User $client, int $limit = 10): Collection
    {
        return AdminAuditLog::query()
            ->where('model_type', User::class)
            ->where('model_id', $client->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function clients(): Builder
    {
        return User::query()->whereHas('role', fn (Builder $q) => $q->where('slug', self::ROLE));
    }

    /** Adds failed_sign_ins (within the alert's days) and shared_phone (other accounts with its phone number). */
    private function withAlerts(Builder $query): Builder
    {
        return $query->addSelect([
            'failed_sign_ins' => fn (QueryBuilder $sub) => $this->failedSignIns($sub),
            'shared_phone'    => fn (QueryBuilder $sub) => $this->samePhone($sub->selectRaw('count(*)')),
        ]);
    }

    private function failedSignIns(QueryBuilder $sub): QueryBuilder
    {
        return $sub->selectRaw('count(*)')
            ->from('activity_logs')
            ->where('activity_logs.model_type', User::class)
            ->whereColumn('activity_logs.model_id', 'users.id')
            ->where('activity_logs.action', AuthService::FAILED_SIGN_IN)
            ->where('activity_logs.created_at', '>=', now()->subDays(AccountAlerts::DAYS));
    }

    /** Other clients - never staff - with the same phone number, as typed. */
    private function samePhone(QueryBuilder $sub): QueryBuilder
    {
        return $sub->from('users as others')
            ->whereColumn('others.phone', 'users.phone')
            ->whereColumn('others.id', '!=', 'users.id')
            ->where('others.phone', '!=', '')
            ->whereExists(fn (QueryBuilder $role) => $role->selectRaw('1')->from('roles')->whereColumn('roles.id', 'others.role_id')->where('roles.slug', self::ROLE));
    }
}
