<?php

namespace App\Repositories\User;

use App\Models\User\Contact;
use App\Models\User\CrmActivity;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\CrmOption;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A business's CRM activities (Doc 17 Phase E1): the Activities page's,
 * narrowed to those the user sees (ActivityVisibility's scope), and a
 * record's own.
 */
class CrmActivityRepository
{
    /** What a row shows - and what deciding who sees it reads of its lead or opportunity. */
    private const SHOWN_WITH = [
        'type:id,key,name,needs_outcome', 'assignee:id,first_name,last_name', 'contact:id,name',
        'lead:id,name,location_id,owner_id,sales_team_id', 'opportunity:id,name,location_id,owner_id,sales_team_id',
        'completer:id,first_name,last_name', 'canceller:id,first_name,last_name',
    ];

    /**
     * The Activities page: those $scope sees that $filters find. The status
     * is "planned" to start, the soonest due first - or "today" (due from
     * $filters['day_start'] to ['day_end']), "overdue" (due before
     * ['now']), "completed" or "cancelled" (the latest first), or "all".
     * Then by whom it is given to, by type, and searched by its subject or
     * its record's name.
     */
    public function paginateFor(Workspace $workspace, array $filters, array $scope, int $perPage): LengthAwarePaginator
    {
        $status = ($filters['status'] ?? '') ?: 'planned';

        $query = $this->visible($workspace, $scope)
            ->with(self::SHOWN_WITH)
            ->when(in_array($status, ['planned', 'today', 'overdue'], true), fn (Builder $query) => $query->where('status', CrmActivity::PLANNED))
            ->when($status === 'today', fn (Builder $query) => $query->whereBetween('due_at', [$filters['day_start'], $filters['day_end']]))
            ->when($status === 'overdue', fn (Builder $query) => $query->where('due_at', '<', $filters['now']))
            ->when(in_array($status, [CrmActivity::COMPLETED, CrmActivity::CANCELLED], true), fn (Builder $query) => $query->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, $id) => $query->where('assigned_to', $id))
            ->when($filters['type_id'] ?? null, fn (Builder $query, $id) => $query->where('type_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('lead', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('opportunity', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))));

        $query = match ($status) {
            CrmActivity::COMPLETED => $query->orderByDesc('completed_at')->orderByDesc('id'),
            CrmActivity::CANCELLED => $query->orderByDesc('cancelled_at')->orderByDesc('id'),
            'all'                  => $query->orderByDesc('due_at')->orderByDesc('id'),
            default                => $query->orderBy('due_at')->orderBy('id'),
        };

        return $query->paginate($perPage);
    }

    /**
     * A record's activities - those whose $column ("lead_id",
     * "opportunity_id" or "contact_id") is $id: the planned ones, the
     * soonest due first, then the latest 100 done or cancelled.
     *
     * @return Collection<int, CrmActivity>
     */
    public function forRecord(Workspace $workspace, string $column, int $id): Collection
    {
        $base = fn () => CrmActivity::query()->where('workspace_id', $workspace->id)->where($column, $id)->with(self::SHOWN_WITH);

        return $base()->where('status', CrmActivity::PLANNED)->orderBy('due_at')->orderBy('id')->get()
            ->concat($base()->where('status', '!=', CrmActivity::PLANNED)->orderByDesc(DB::raw('coalesce(completed_at, cancelled_at)'))->orderByDesc('id')->limit(100)->get());
    }

    /** One of the business's, with what its row shows. Who may see it is the caller's to decide. */
    public function findFor(Workspace $workspace, int $id): ?CrmActivity
    {
        return $workspace->crmActivities()->with(self::SHOWN_WITH)->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmActivity
    {
        return $workspace->crmActivities()->create($data);
    }

    public function update(CrmActivity $activity, array $data): CrmActivity
    {
        $activity->update($data);

        return $activity;
    }

    /** Its row, locked until the change is committed: two completions at once wait for each other. */
    public function lock(CrmActivity $activity): CrmActivity
    {
        return CrmActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
    }

    /** Read again after a save, with what its row shows. */
    public function loadShown(CrmActivity $activity): CrmActivity
    {
        return $activity->refresh()->load(self::SHOWN_WITH);
    }

    /** A lead's activities go with it into its opportunity (Phase E1): they are the opportunity's now, and stay the lead's too. */
    public function carryToOpportunity(CrmLead $lead, CrmOpportunity $opportunity): void
    {
        CrmActivity::query()->where('lead_id', $lead->id)->update(['opportunity_id' => $opportunity->id, 'updated_at' => now()]);
    }

    /**
     * Every business's planned activities whose reminder's moment has come by
     * $now and was never sent (Phase E2), the earliest first - at most
     * $limit - with what their notices name and who decides who sees them.
     *
     * @return Collection<int, CrmActivity>
     */
    public function dueReminders(CarbonInterface $now, int $limit): Collection
    {
        return CrmActivity::query()
            ->where('status', CrmActivity::PLANNED)
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', $now)
            ->whereNull('reminded_at')
            ->with(['workspace', 'assignee', 'type:id,name', 'contact:id,name', 'lead:id,name,location_id,owner_id,sales_team_id', 'opportunity:id,name,location_id,owner_id,sales_team_id'])
            ->orderBy('remind_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** Marks $activity's reminder sent: true for the one run that did so first. */
    public function claimReminder(CrmActivity $activity): bool
    {
        return CrmActivity::query()->whereKey($activity->id)->whereNull('reminded_at')->update(['reminded_at' => now()]) === 1;
    }

    /** Whether an activity is for $contact: one that has one is deactivated, never deleted. */
    public function usesContact(Contact $contact): bool
    {
        return CrmActivity::query()->where('contact_id', $contact->id)->exists();
    }

    /** Whether an activity is of $type: one that is is switched off, never deleted. */
    public function usesType(CrmOption $type): bool
    {
        return CrmActivity::query()->where('type_id', $type->id)->exists();
    }

    /**
     * The CRM Overview's overdue activities (Phase G1): planned ones $scope
     * sees, due before $now - given to $filters' owner_id, at its
     * location_id, when they name one.
     */
    public function overdueCount(Workspace $workspace, array $scope, array $filters, CarbonInterface $now): int
    {
        return $this->planned($workspace, $scope, $filters)->where('due_at', '<', $now)->count();
    }

    /**
     * The Dashboard's activities due today (Phase G4): planned ones $scope
     * sees, due from $from to $to - the business's day - narrowed as
     * overdueCount()'s, as the Activities page's Due Today lists them.
     */
    public function dueCount(Workspace $workspace, array $scope, array $filters, CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->planned($workspace, $scope, $filters)->whereBetween('due_at', [$from, $to])->count();
    }

    /**
     * What CRM's Activity Completion counts (Phase H3): those $scope sees,
     * narrowed as overdueCount()'s, that were due between $from and $to -
     * with their type and whom they are given to.
     */
    public function dueBetween(Workspace $workspace, array $scope, array $filters, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)
            ->whereBetween('due_at', [$from, $to])
            ->with(['type:id,name', 'assignee:id,first_name,last_name'])
            ->get(['id', 'type_id', 'assigned_to', 'status', 'due_at', 'completed_at']);
    }

    /** The planned ones $scope sees due before $now (Phase H3's Overdue Activities), narrowed as overdueCount()'s, the longest overdue first - with what names them and their record. */
    public function overdue(Workspace $workspace, array $scope, array $filters, CarbonInterface $now): Collection
    {
        return $this->planned($workspace, $scope, $filters)
            ->where('due_at', '<', $now)
            ->with(['type:id,name', 'assignee:id,first_name,last_name', 'lead:id,name', 'opportunity:id,name', 'contact:id,name'])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get(['id', 'subject', 'type_id', 'assigned_to', 'due_at', 'lead_id', 'opportunity_id', 'contact_id']);
    }

    /** Those $scope sees completed between $from and $to (Phase H3's performance reports), narrowed as overdueCount()'s - with whom they were given to and their record's sales team. */
    public function completedBetween(Workspace $workspace, array $scope, array $filters, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)
            ->where('status', CrmActivity::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->with(['assignee:id,first_name,last_name', 'lead:id,sales_team_id', 'lead.team:id,name', 'opportunity:id,sales_team_id', 'opportunity.team:id,name'])
            ->get(['id', 'assigned_to', 'completed_at', 'lead_id', 'opportunity_id']);
    }

    /**
     * When the latest activity done for each of opportunities $ids was
     * completed (Phase H3), by opportunity id: as lastDoneAt(), for many.
     *
     * @return array<int, string>
     */
    public function lastDoneByOpportunity(Workspace $workspace, array $ids): array
    {
        return CrmActivity::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('opportunity_id', $ids)
            ->where('status', CrmActivity::COMPLETED)
            ->groupBy('opportunity_id')
            ->selectRaw('opportunity_id, max(completed_at) as last_at')
            ->pluck('last_at', 'opportunity_id')
            ->all();
    }

    /** Planned ones $scope sees - given to $filters' owner_id, at its location_id, when they name one. */
    private function planned(Workspace $workspace, array $scope, array $filters): Builder
    {
        return $this->narrowed($this->visible($workspace, $scope), $filters)->where('status', CrmActivity::PLANNED);
    }

    /** $query given to $filters' owner_id, at its location_id, when they name one. */
    private function narrowed(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['owner_id'] ?? null, fn (Builder $query, $id) => $query->where('assigned_to', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id));
    }

    /**
     * When the latest of a record's activities - those whose $column
     * ("lead_id" or "opportunity_id") is $id - was done (Phase G3a).
     */
    public function lastDoneAt(Workspace $workspace, string $column, int $id): ?CarbonInterface
    {
        return CrmActivity::query()
            ->where('workspace_id', $workspace->id)
            ->where($column, $id)
            ->where('status', CrmActivity::COMPLETED)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first(['id', 'completed_at'])
            ?->completed_at;
    }

    /** A record's soonest planned activity (Phase G3a) - overdue ones first - with what names it. */
    public function nextPlanned(Workspace $workspace, string $column, int $id): ?CrmActivity
    {
        return CrmActivity::query()
            ->where('workspace_id', $workspace->id)
            ->where($column, $id)
            ->where('status', CrmActivity::PLANNED)
            ->with('type:id,name')
            ->orderBy('due_at')
            ->orderBy('id')
            ->first(['id', 'type_id', 'subject', 'due_at']);
    }

    /** $workspace's activities that $scope sees (ActivityVisibility::scopeFor): a contact's, with no location, at any. */
    private function visible(Workspace $workspace, array $scope): Builder
    {
        return CrmActivity::query()
            ->where('workspace_id', $workspace->id)
            ->when($scope['location_ids'] !== null, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('location_id')
                ->orWhereIn('location_id', $scope['location_ids'])))
            ->when(! $scope['all'], fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('assigned_to', $scope['user_id'])
                ->when($scope['member_ids'], fn (Builder $query, array $ids) => $query->orWhereIn('assigned_to', $ids))));
    }
}
