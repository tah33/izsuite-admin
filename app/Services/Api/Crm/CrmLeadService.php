<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesTeamRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmLeadRules;
use App\Services\Crm\LeadAssignments;
use App\Services\Crm\LeadInterests;
use App\Services\Crm\LeadVisibility;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\EditConflicts;
use App\Services\Shared\LocationAccess;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Leads (Doc 17 sections 17.6 to 17.9 - Phase B1): the Leads page, one lead,
 * what its form chooses from, adding one and changing one. Its steps are
 * CrmLeadFlow's.
 *
 * A lead comes from one of Settings > CRM's lead sources and is at one of
 * the business's locations - where the user's new records start, when none
 * is chosen. Whoever adds it owns it, unless someone who may assign leads
 * gives it to another member or leaves it in the queue. A lead keeps its
 * source, team and location when they are switched off later. Each save is
 * logged by name.
 */
class CrmLeadService
{
    use RefusesConflicts;

    /** What the form saves, its interested items aside; the owner is chosen on adding, then by assigning. */
    public const FIELDS = [
        'name', 'business_name', 'job_title', 'email', 'phone', 'source_id', 'source_details', 'sales_team_id', 'location_id',
        'priority', 'estimated_value', 'currency', 'expected_purchase_date', ...CrmLead::ADDRESS_FIELDS, 'preferred_language', 'tags', 'notes',
        // Its qualification (Phase B2a).
        ...CrmLead::QUALIFICATION_FIELDS,
    ];

    public function __construct(
        protected CrmLeadRepository $leads,
        protected CrmOptionRepository $options,
        protected SalesTeamRepository $teams,
        protected LocationRepository $locations,
        protected CrmSettingService $settings,
        protected CrmLeadRules $rules,
        protected LeadVisibility $visibility,
        protected LeadAssignments $assignments,
        protected LeadInterests $interests,
        protected LocationAccess $locationAccess,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace, User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        if (($filters['owner_id'] ?? null) === 'me') {
            $filters['owner_id'] = $user->id;
        }

        return $this->leads->paginateFor($workspace, $filters, $this->visibility->scopeFor($workspace, $user), $perPage);
    }

    /** Another business's lead - or one $user does not see - answers like one that does not exist. */
    public function find(Workspace $workspace, User $user, int $id): CrmLead
    {
        return $this->leads->findFor($workspace, $id, $this->visibility->scopeFor($workspace, $user)) ?? abort(404, 'Lead not found.');
    }

    /**
     * What a lead's form and the Leads page's filters choose from: the lead
     * sources and sales teams switched on, the people a lead can be given
     * to, where a new lead starts and who owns it - $user, unless they choose
     * someone else - and what qualifying needs filled in (Phase B2a). The
     * lists start here if Settings > CRM was never opened.
     */
    public function options(Workspace $workspace, User $user): array
    {
        $this->settings->start($workspace);

        return [
            'sources'                => $this->options->getFor($workspace)->where('kind', 'lead_source')->where('is_active', true)->values(),
            'teams'                  => $this->teams->forWorkspace($workspace, true),
            'owners'                 => $this->assignments->owners($workspace),
            'location_id'            => $this->locationAccess->defaultFor($workspace),
            'owner_id'               => $user->id,
            'qualification_required' => $this->settings->qualificationRequired($workspace),
        ];
    }

    /** $data is validated (see SaveCrmLeadRequest); owner_id left out is the user, null the queue. */
    public function create(Workspace $workspace, User $user, array $data): CrmLead
    {
        $fields  = $this->checked($workspace, $data);
        $ownerId = array_key_exists('owner_id', $data) ? ($data['owner_id'] === null ? null : (int) $data['owner_id']) : $user->id;

        if ($ownerId !== $user->id && ! $this->access->can('crm.leads.assign')) {
            throw ValidationException::withMessages(['owner_id' => 'You add leads for yourself. Giving one to someone else, or leaving it in the queue, needs Assign Leads.']);
        }

        if ($ownerId !== null) {
            $this->assignments->ensureOwner($workspace, $ownerId, $fields['location_id']);
        }

        $items = $this->interests->rows($workspace, $data['interested_items'] ?? []);

        $lead = DB::transaction(function () use ($workspace, $fields, $ownerId, $items) {
            $lead = $this->leads->create($workspace, [
                ...$fields,
                'owner_id'    => $ownerId,
                'status'      => $ownerId === null ? CrmLead::NEW : CrmLead::ASSIGNED,
                'assigned_at' => $ownerId === null ? null : now(),
            ]);

            $this->leads->replaceItems($lead, $items);

            return $lead;
        });
        $lead = $this->leads->loadShown($lead);

        ActivityLogService::record('created', 'Added '.$this->describe($lead).($lead->owner && $ownerId !== $user->id ? " for {$lead->owner->name}" : ''), $lead, ['source' => 'api']);

        $this->assignments->notify($workspace, $lead, $user);

        return $lead;
    }

    /** An open lead's fields; $data is validated (see SaveCrmLeadRequest). */
    public function update(Workspace $workspace, User $user, int $id, array $data): CrmLead
    {
        $lead = $this->find($workspace, $user, $id);

        if (! $this->rules->allows($lead, 'edit')) {
            $this->refuse('An unqualified or archived lead is reopened before it is changed.');
        }

        EditConflicts::ensureUnchanged($lead, 'this lead');

        $fields = $this->checked($workspace, $data, $lead);

        // Its owner follows it to its new location, or it stays where it is.
        if ($lead->owner_id && array_key_exists('location_id', $fields) && $fields['location_id'] !== $lead->location_id) {
            $this->assignments->ensureOwner($workspace, $lead->owner_id, $fields['location_id'], 'location_id');
        }

        // Left out, its interested items stay as they are.
        $items        = array_key_exists('interested_items', $data) ? $this->interests->rows($workspace, $data['interested_items'] ?? []) : null;
        $itemsChanged = $items !== null && $this->interests->changed($lead, $items);

        DB::transaction(function () use ($lead, $fields, $items, $itemsChanged) {
            $this->leads->update($lead, $fields);

            // Its version moves with its items too, so another person's save of them is caught (EditConflicts).
            if ($itemsChanged) {
                $this->leads->replaceItems($lead, $items);
                $this->leads->touch($lead);
            }
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($lead), $lead, [
            'source' => 'api',
            'fields' => [
                ...array_keys(array_diff_key($lead->getChanges(), ['updated_at' => true, 'updated_by' => true])),
                ...($itemsChanged ? ['interested_items'] : []),
            ],
        ]);

        return $this->leads->loadShown($lead);
    }

    /** What the log calls it: 'lead "Rahim Uddin"'. */
    public function describe(CrmLead $lead): string
    {
        return 'lead "'.Str::limit($lead->name, 100).'"';
    }

    /**
     * $data's fields, with its source, team and location the business's own
     * and switched on - or the lead's already - and a value's currency the
     * base one unless chosen.
     */
    private function checked(Workspace $workspace, array $data, ?CrmLead $lead = null): array
    {
        $fields = Arr::only($data, self::FIELDS);

        if (array_key_exists('source_id', $fields)) {
            $source = $this->options->findFor($workspace, (int) $fields['source_id']);

            if (! $source || $source->kind !== 'lead_source' || (! $source->is_active && $source->id !== $lead?->source_id)) {
                throw ValidationException::withMessages(['source_id' => 'Choose one of the lead sources in Settings → CRM.']);
            }
        }

        $team = isset($fields['sales_team_id']) ? (int) $fields['sales_team_id'] : null;

        if ($team !== null && $team !== $lead?->sales_team_id && ! $this->teams->isActive($workspace, $team)) {
            throw ValidationException::withMessages(['sales_team_id' => 'Choose one of the sales teams switched on.']);
        }

        if ($lead === null || array_key_exists('location_id', $fields)) {
            $fields['location_id'] = $this->location($workspace, isset($fields['location_id']) ? (int) $fields['location_id'] : null, $lead);
        }

        // A value or a budget is in the lead's currency: the base one unless chosen.
        if ((isset($fields['estimated_value']) || isset($fields['budget'])) && empty($fields['currency'])) {
            $fields['currency'] = $lead?->currency ?? $workspace->base_currency;
        }

        return $fields;
    }

    /** Where the lead is: a location of the business, switched on and the user's to use - or its own - else where their new records start. */
    private function location(Workspace $workspace, ?int $id, ?CrmLead $lead): ?int
    {
        if ($id === null) {
            return $lead?->location_id ?? $this->locationAccess->defaultFor($workspace);
        }

        $location = $id === $lead?->location_id ? null : $this->locations->findFor($workspace, $id);

        if ($id !== $lead?->location_id && (! $location || ! $location->is_active || ! $this->access->usesLocation($id))) {
            throw ValidationException::withMessages(['location_id' => 'Choose one of your locations that is switched on.']);
        }

        return $id;
    }
}
