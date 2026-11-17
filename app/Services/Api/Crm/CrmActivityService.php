<?php

namespace App\Services\Api\Crm;

use App\Models\User\Contact;
use App\Models\User\CrmActivity;
use App\Models\User\CrmOption;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\ActivityAssignments;
use App\Services\Crm\ActivityVisibility;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Support\SettingChanges;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRM activities (Doc 17 section 17.17 - Phase E1): the Activities page, a
 * record's activities, planning one for a lead, an opportunity or a contact
 * the user sees, changing a planned one, and completing it - with an outcome
 * where its type needs one, and a follow-up planned straight after - or
 * cancelling it. Days and times are the business's; an activity is due at a
 * moment, kept in UTC. Each step is logged on the record it is for.
 */
class CrmActivityService
{
    use RefusesConflicts;

    public function __construct(
        protected CrmActivityRepository $activities,
        protected CrmOptionRepository $options,
        protected ContactRepository $contacts,
        protected CrmLeadService $leads,
        protected CrmOpportunityService $opportunities,
        protected CrmSettingService $settings,
        protected ActivityVisibility $visibility,
        protected ActivityAssignments $assignments,
        protected BusinessCalendar $calendar,
        protected WorkspaceAccess $access,
    ) {}

    /** The Activities page (see CrmActivityRepository::paginateFor), "me" given for whom it is given to being the user. */
    public function list(Workspace $workspace, User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        if (($filters['assigned_to'] ?? null) === 'me') {
            $filters['assigned_to'] = $user->id;
        }

        $today = $this->calendar->today($workspace);
        $page  = $this->activities->paginateFor($workspace, [
            ...$filters,
            'now'       => now(),
            'day_start' => $today->utc(),
            'day_end'   => $today->endOfDay()->utc(),
        ], $this->visibility->scopeFor($workspace, $user), $perPage);

        $this->mark($workspace, $page->items());

        return $page;
    }

    /**
     * The activities of the lead, opportunity or contact - $kind - that $id
     * is: every one, for whoever sees the record.
     *
     * @return Collection<int, CrmActivity>
     */
    public function forRecord(Workspace $workspace, User $user, string $kind, int $id): Collection
    {
        $this->record($workspace, $user, $kind, $id);

        $activities = $this->activities->forRecord($workspace, CrmActivity::FOR[$kind], $id);
        $this->mark($workspace, $activities->all());

        return $activities;
    }

    /**
     * What an activity's form chooses from: the activity types switched on
     * - whether each needs an outcome, and which is a call - the people it
     * can be given to with the locations each may use, who the user is, and
     * the records the user may plan one for.
     */
    public function options(Workspace $workspace, User $user): array
    {
        return [
            'types'     => $this->types($workspace),
            'assignees' => $this->assignments->assignees($workspace),
            'user_id'   => $user->id,
            'kinds'     => array_values(array_filter([
                $this->settings->usesLeads($workspace) && $this->access->allows($user, $workspace, 'crm.leads.view') ? 'lead' : null,
                $this->access->allows($user, $workspace, 'crm.opportunities.view') ? 'opportunity' : null,
                $this->access->allows($user, $workspace, 'contacts.view') ? 'contact' : null,
            ])),
        ];
    }

    /**
     * Up to ten leads, opportunities or contacts - $kind - matching $search
     * that the user may plan an activity for: what the Activities page's
     * form picks from.
     *
     * @return list<array{id: int, name: string, detail: string|null}>
     */
    public function regarding(Workspace $workspace, User $user, string $kind, ?string $search): array
    {
        $this->ensureMayView($workspace, $user, $kind);

        return match ($kind) {
            'lead'        => collect($this->leads->list($workspace, $user, ['search' => $search], 10)->items())
                ->map(fn ($lead) => ['id' => $lead->id, 'name' => $lead->name, 'detail' => $lead->business_name])->all(),
            'opportunity' => collect($this->opportunities->list($workspace, $user, ['search' => $search, 'status' => 'all'], 10)->items())
                ->map(fn ($opportunity) => ['id' => $opportunity->id, 'name' => $opportunity->name, 'detail' => $opportunity->contact?->name])->all(),
            default       => collect($this->contacts->paginateFor($workspace, ['search' => $search, 'status' => 'active', 'unmerged' => true], 10, false)->items())
                ->map(fn (Contact $contact) => ['id' => $contact->id, 'name' => $contact->name, 'detail' => $contact->contact_number])->all(),
        };
    }

    /** $data is validated (see SaveCrmActivityRequest): for exactly one of a lead, an opportunity or a contact. */
    public function create(Workspace $workspace, User $user, array $data): CrmActivity
    {
        $kind   = collect(CrmActivity::FOR)->search(fn (string $column) => ! empty($data[$column]));
        $record = $this->record($workspace, $user, $kind, (int) $data[CrmActivity::FOR[$kind]], true);

        $activity = DB::transaction(function () use ($workspace, $user, $data, $kind, $record) {
            $activity = $this->activities->create($workspace, [
                ...$this->fields($workspace, $data, $record->getAttribute('location_id'), $user->id),
                CrmActivity::FOR[$kind] => $record->getKey(),
                'location_id'           => $record->getAttribute('location_id'),
                'created_by'            => $user->id,
            ]);

            ActivityLogService::record('created', 'Planned '.$this->describe($activity), $record, ['source' => 'api', 'changes' => []]);

            return $activity;
        });

        return $this->shown($workspace, $activity);
    }

    /** $data is validated (see SaveCrmActivityRequest). A planned one only; what it is for stays. */
    public function update(Workspace $workspace, User $user, int $id, array $data): CrmActivity
    {
        $activity = $this->planned($workspace, $user, $id);

        DB::transaction(function () use ($workspace, $user, $data, $activity) {
            $this->activities->update($activity, $this->fields($workspace, $data, $activity->location_id, $activity->assigned_to ?? $user->id));

            ActivityLogService::record('updated', 'Updated '.$this->describe($activity), $activity->regarding(), ['source' => 'api', 'changes' => $this->readable($workspace, SettingChanges::of($activity))]);
        });

        return $this->shown($workspace, $activity);
    }

    /**
     * $data is validated (see CompleteCrmActivityRequest): its outcome -
     * needed where its type says so - and perhaps a follow-up to plan for
     * the same record straight after.
     *
     * @return array{activity: CrmActivity, follow_up: CrmActivity|null}
     */
    public function complete(Workspace $workspace, User $user, int $id, array $data): array
    {
        $activity = $this->planned($workspace, $user, $id);
        $outcome  = trim((string) ($data['outcome'] ?? '')) ?: null;

        if ($outcome === null && $activity->type?->needs_outcome) {
            throw ValidationException::withMessages(['outcome' => "A {$activity->type->name} needs its outcome once it is done."]);
        }

        $followUp = DB::transaction(function () use ($workspace, $user, $data, $activity, $outcome) {
            if ($this->activities->lock($activity)->status !== CrmActivity::PLANNED) {
                $this->refuse('This activity is completed or cancelled already.');
            }

            $this->activities->update($activity, ['status' => CrmActivity::COMPLETED, 'outcome' => $outcome, 'completed_at' => now(), 'completed_by' => $user->id]);
            ActivityLogService::record('updated', 'Completed '.$this->describe($activity), $activity->regarding(), ['source' => 'api', 'outcome' => $outcome, 'changes' => []]);

            if (empty($data['follow_up'])) {
                return null;
            }

            $next = $this->activities->create($workspace, [
                ...$this->fields($workspace, $data['follow_up'], $activity->location_id, $user->id, 'follow_up.'),
                ...collect(CrmActivity::FOR)->mapWithKeys(fn (string $column) => [$column => $activity->{$column}])->all(),
                'location_id'  => $activity->location_id,
                'follow_up_of' => $activity->id,
                'created_by'   => $user->id,
            ]);
            ActivityLogService::record('created', 'Planned '.$this->describe($next).', following up '.$this->describe($activity), $activity->regarding(), ['source' => 'api', 'changes' => []]);

            return $next;
        });

        return ['activity' => $this->shown($workspace, $activity), 'follow_up' => $followUp ? $this->shown($workspace, $followUp) : null];
    }

    /** A planned one only. */
    public function cancel(Workspace $workspace, User $user, int $id): CrmActivity
    {
        $activity = $this->planned($workspace, $user, $id);

        DB::transaction(function () use ($user, $activity) {
            if ($this->activities->lock($activity)->status !== CrmActivity::PLANNED) {
                $this->refuse('This activity is completed or cancelled already.');
            }

            $this->activities->update($activity, ['status' => CrmActivity::CANCELLED, 'cancelled_at' => now(), 'cancelled_by' => $user->id]);
            ActivityLogService::record('updated', 'Cancelled '.$this->describe($activity), $activity->regarding(), ['source' => 'api', 'changes' => []]);
        });

        return $this->shown($workspace, $activity);
    }

    /** Another business's activity - or one $user does not see - answers like one that does not exist. */
    public function find(Workspace $workspace, User $user, int $id): CrmActivity
    {
        $activity = $this->activities->findFor($workspace, $id);

        return $activity && $this->visibility->sees($workspace, $user, $activity) ? $activity : abort(404, 'Activity not found.');
    }

    /** 'Call "Discuss the quotation"' - its type and subject, for the Activity Log. */
    public function describe(CrmActivity $activity): string
    {
        return ($activity->type?->name ?? 'Activity').' "'.Str::limit($activity->subject, 100).'"';
    }

    /**
     * Their due day and time in the business's time zone (due_on, due_time),
     * and whether a planned one is overdue: its moment has passed.
     *
     * @param  iterable<CrmActivity>  $activities
     */
    public function mark(Workspace $workspace, iterable $activities): void
    {
        $zone = $this->calendar->timezone($workspace);
        $now  = now();

        foreach ($activities as $activity) {
            $due = $activity->due_at->copy()->setTimezone($zone);

            $activity->setAttribute('due_on', $due->toDateString());
            $activity->setAttribute('due_time', $due->format('H:i'));
            $activity->setAttribute('overdue', $activity->status === CrmActivity::PLANNED && $activity->due_at->lt($now));
        }
    }

    /** Its fields from $data (with $prefix for a follow-up's errors): its type switched on, given to someone who may use $locationId - $defaultAssignee when none is given - and due then, in UTC. */
    private function fields(Workspace $workspace, array $data, ?int $locationId, int $defaultAssignee, string $prefix = ''): array
    {
        $type = $this->types($workspace)->firstWhere('id', (int) $data['type_id'])
            ?? throw ValidationException::withMessages([$prefix.'type_id' => 'Choose one of your activity types switched on.']);

        $assignee = (int) ($data['assigned_to'] ?? $defaultAssignee);
        $this->assignments->ensureAssignee($workspace, $assignee, $locationId, $prefix.'assigned_to');

        return [
            'type_id'          => $type->id,
            'subject'          => trim($data['subject']),
            'assigned_to'      => $assignee,
            'due_at'           => CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data['due_on']} {$data['due_time']}", $this->calendar->timezone($workspace))->utc(),
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'priority'         => $data['priority'] ?? null,
            'place'            => trim((string) ($data['place'] ?? '')) ?: null,
            // Only a call has a direction (Doc 17 section 17.18).
            'call_direction'   => $type->key === 'call' ? ($data['call_direction'] ?? null) : null,
            'description'      => trim((string) ($data['description'] ?? '')) ?: null,
        ];
    }

    /**
     * $changes as a person reads them on the record's timeline: when it is
     * due in the business's time zone, not UTC, and its type and whom it is
     * given to by name.
     */
    private function readable(Workspace $workspace, array $changes): array
    {
        $zone   = $this->calendar->timezone($workspace);
        $types  = $this->options->getFor($workspace)->where('kind', 'activity_type')->pluck('name', 'id');
        $people = collect($this->assignments->assignees($workspace))->pluck('name', 'id');
        $local  = fn ($moment) => $moment ? CarbonImmutable::parse($moment, 'UTC')->setTimezone($zone)->format('Y-m-d H:i') : $moment;

        return array_map(fn (array $change) => match ($change['setting']) {
            'due_at'      => [...$change, 'from' => $local($change['from']), 'to' => $local($change['to'])],
            'type_id'     => ['setting' => 'type', 'from' => $types[(int) $change['from']] ?? $change['from'], 'to' => $types[(int) $change['to']] ?? $change['to']],
            'assigned_to' => [...$change, 'from' => $people[(int) $change['from']] ?? $change['from'], 'to' => $people[(int) $change['to']] ?? $change['to']],
            default       => $change,
        }, $changes);
    }

    /** @return Collection<int, CrmOption> the activity types switched on, in order - its lists started if Settings → CRM never was */
    private function types(Workspace $workspace): Collection
    {
        $this->settings->start($workspace);

        return $this->options->getFor($workspace)->where('kind', 'activity_type')->where('is_active', true)->values();
    }

    /**
     * The lead, opportunity or contact - $kind - that $id is, if $user may
     * view its kind and sees it; else not found. One planned for ($adding)
     * is no merged contact.
     */
    private function record(Workspace $workspace, User $user, string $kind, int $id, bool $adding = false): Model
    {
        $this->ensureMayView($workspace, $user, $kind);

        $record = match ($kind) {
            'lead'        => $this->leads->find($workspace, $user, $id),
            'opportunity' => $this->opportunities->find($workspace, $user, $id),
            default       => $this->contacts->findFor($workspace, $id) ?? abort(404, 'Contact not found.'),
        };

        if ($adding && $record instanceof Contact && $record->isMerged()) {
            $this->refuse('This contact was merged into another: plan the activity for that one.');
        }

        return $record;
    }

    /** Refused unless $user may view $kind's records: leads, opportunities or contacts. */
    private function ensureMayView(Workspace $workspace, User $user, string $kind): void
    {
        $permission = ['lead' => 'crm.leads.view', 'opportunity' => 'crm.opportunities.view', 'contact' => 'contacts.view'][$kind];

        if (! $this->access->allows($user, $workspace, $permission)) {
            abort(403, 'Your role does not allow this.');
        }
    }

    /** One $user sees that is still planned: a completed or cancelled one stays as it is. */
    private function planned(Workspace $workspace, User $user, int $id): CrmActivity
    {
        $activity = $this->find($workspace, $user, $id);

        if (! $activity->isPlanned()) {
            $this->refuse('A completed or cancelled activity stays as it is.');
        }

        return $activity;
    }

    private function shown(Workspace $workspace, CrmActivity $activity): CrmActivity
    {
        $activity = $this->activities->loadShown($activity);
        $this->mark($workspace, [$activity]);

        return $activity;
    }
}
