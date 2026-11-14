<?php

namespace App\Repositories\Admin;

use App\Models\Admin\AdminAuditLog;
use App\Models\Billing\Package;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Shared\ActivityLog;
use App\Models\User\BusinessSetting;
use App\Models\User\Ticket;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The platform's businesses for Admin > Businesses (Doc 15 section 15.5):
 * each with its owner, country, plan, subscription and status, its users
 * (active or invited), active locations, storage and last activity.
 */
class BusinessRepository
{
    /**
     * A page of businesses, newest first. $filters: search (name, ID or the
     * owner's name or e-mail), status, package (key), country, subscription
     * (free, trialing, awaiting_payment, active, cancellation_scheduled,
     * grace), from and to (sign-up dates), and limits ("reached": at a limit
     * above one, or past any).
     */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)->latest('workspaces.id')->paginate($perPage)->withQueryString();
    }

    /** One business, with all its page shows; null when there is none. */
    public function findForPage(int $id): ?Workspace
    {
        return $this->withCounts(Workspace::query())
            ->withCount(['contacts', 'items'])
            ->with([
                'billing', 'businessSetting',
                'workspaceApps' => fn ($query) => $query->where('status', WorkspaceApp::STATUS_ACTIVE)->with('app:id,name,edition,sort_order'),
            ])
            ->find($id);
    }

    /** @return list<string> the countries businesses are in, by code */
    public function countries(): array
    {
        return BusinessSetting::whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country')->all();
    }

    /** @return Collection<int, Ticket> its latest support cases (Doc 15 section 15.22), with who opened each */
    public function supportCases(Workspace $workspace, int $limit = 10): Collection
    {
        return $workspace->supportCases()
            ->with('user:id,first_name,last_name')
            ->latest('updated_at')
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'workspace_id', 'user_id', 'subject', 'status', 'priority', 'updated_at']);
    }

    /** @return Collection<int, AdminAuditLog> the latest admin entries about $workspace: it, or a record of it */
    public function adminActivity(Workspace $workspace, int $limit = 10): Collection
    {
        return AdminAuditLog::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('model_type', Workspace::class)->where('model_id', $workspace->id))
                ->orWhere('properties->workspace_id', $workspace->id))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * $query's businesses narrowed by $filters - status, package (key),
     * country, subscription and app (the ID of an app it has active) - as
     * Admin > Businesses and Admin > Overview (Doc 15 Phase D1) filter them.
     */
    public function narrow(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('workspaces.status', $status))
            ->when($filters['package'] ?? null, fn (Builder $query, string $key) => $query->whereIn('workspaces.package_id', Package::query()->select('id')->where('key', $key)))
            ->when($filters['country'] ?? null, fn (Builder $query, string $country) => $query->whereHas('businessSetting', fn (Builder $setting) => $setting->where('country', $country)))
            ->when($filters['subscription'] ?? null, fn (Builder $query, string $status) => $this->onSubscription($query, $status))
            ->when($filters['app'] ?? null, fn (Builder $query, int|string $app) => $query->whereHas('workspaceApps', fn (Builder $apps) => $apps->where('app_id', (int) $app)->where('status', WorkspaceApp::STATUS_ACTIVE)));
    }

    private function query(array $filters): Builder
    {
        $query = $this->withCounts(Workspace::query()->select('workspaces.*'))
            ->leftJoin('packages', 'packages.id', '=', 'workspaces.package_id')
            ->addSelect(['packages.user_limit as plan_user_limit', 'packages.location_limit as plan_location_limit', 'packages.storage_limit_mb as plan_storage_mb'])
            ->with('businessSetting:id,workspace_id,country');

        return $this->narrow($query, $filters)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $id   = (int) ltrim(preg_replace('/^BW-?/i', '', $search) ?? '', '0');

                $query->where(fn (Builder $query) => $query
                    ->where('workspaces.name', 'like', $like)
                    ->when($id > 0 && preg_match('/^(BW-?)?\d+$/i', $search), fn (Builder $query) => $query->orWhere('workspaces.id', $id))
                    ->orWhereHas('user', fn (Builder $owner) => $owner->where('email', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('workspaces.created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('workspaces.created_at', '<=', $to.' 23:59:59'))
            // HAVING reads the counts' aliases; grouped by both keys, as SQLite needs a GROUP BY for it and MySQL's full group-by mode is satisfied.
            ->when(($filters['limits'] ?? null) === 'reached', fn (Builder $query) => $query->groupBy('workspaces.id', 'packages.id')->havingRaw(
                '(plan_user_limit is not null and (users_count > plan_user_limit or (plan_user_limit > 1 and users_count >= plan_user_limit)))'
                .' or (plan_location_limit is not null and (locations_count > plan_location_limit or (plan_location_limit > 1 and locations_count >= plan_location_limit)))'
                .' or (plan_storage_mb is not null and coalesce(storage_bytes, 0) >= plan_storage_mb * 1048576)'
            ));
    }

    /** Its owner, plan and subscription, users (active or invited), active locations, storage and last activity. */
    private function withCounts(Builder $query): Builder
    {
        return $query
            ->with(['user:id,first_name,last_name,email,email_verified_at', 'package:id,key,name,user_limit,location_limit,storage_limit_mb', 'subscription'])
            ->withCount([
                'members as users_count'       => fn (Builder $members) => $members->whereIn('status', WorkspaceMember::COUNTED),
                'locations as locations_count' => fn (Builder $locations) => $locations->where('is_active', true),
            ])
            ->withSum('businessFiles as storage_bytes', 'size')
            ->addSelect(['last_activity_at' => ActivityLog::selectRaw('max(created_at)')->whereColumn('workspace_id', 'workspaces.id')]);
    }

    /** The businesses whose subscription stands at $status - "free" for those with none running. */
    private function onSubscription(Builder $query, string $status): Builder
    {
        return match ($status) {
            'free'                   => $query->whereDoesntHave('subscription', fn (Builder $sub) => $sub->whereIn('status', WorkspaceSubscription::CURRENT)),
            'cancellation_scheduled' => $query->whereHas('subscription', fn (Builder $sub) => $sub->where('status', WorkspaceSubscription::ACTIVE)->where('cancels_at_period_end', true)),
            'active'                 => $query->whereHas('subscription', fn (Builder $sub) => $sub->where('status', WorkspaceSubscription::ACTIVE)->where('cancels_at_period_end', false)),
            default                  => $query->whereHas('subscription', fn (Builder $sub) => $sub->where('status', $status)),
        };
    }
}
