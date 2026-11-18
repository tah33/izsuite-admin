<?php

namespace App\Services\Api\Crm;

use App\Models\User\Contact;
use App\Models\User\CrmOpportunity;
use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesTeamRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmOpportunityRules;
use App\Services\Crm\FollowUpCutoffs;
use App\Services\Crm\LeadInterests;
use App\Services\Crm\OpportunityAssignments;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Crm\PipelineChoice;
use App\Services\Crm\StageRequirements;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\EditConflicts;
use App\Services\Shared\LocationAccess;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Opportunities (Doc 17 sections 17.12 to 17.14 - Phase D1): the
 * Opportunities page, one opportunity, what its form chooses from, adding
 * one and changing one. Moving one between stages is CrmOpportunityFlow's.
 *
 * An opportunity is for one of the business's active contacts - a supplier
 * becomes a customer as well - at an open stage of the business's pipeline,
 * owned by whoever adds it unless they give it to someone else, at one of
 * the business's locations. Its probability is its stage's unless someone
 * who may change probabilities gives another. Each save is logged by name.
 */
class CrmOpportunityService
{
    use RefusesConflicts;

    /** What the form saves, its stage, probability, owner and interested items aside. */
    public const FIELDS = [
        'name', 'contact_id', 'contact_person', 'sales_team_id', 'location_id', 'source_id', 'priority',
        'expected_value', 'currency', 'expected_close_date', 'requirements', 'next_action', 'competitors', 'tags', 'notes',
    ];

    public function __construct(
        protected CrmOpportunityRepository $opportunities,
        protected CrmOptionRepository $options,
        protected SalesTeamRepository $teams,
        protected LocationRepository $locations,
        protected ContactRepository $contacts,
        protected CrmSettingService $settings,
        protected CrmOpportunityRules $rules,
        protected OpportunityVisibility $visibility,
        protected OpportunityAssignments $assignments,
        protected OpportunityForecast $forecast,
        protected LeadInterests $interests,
        protected LocationAccess $locationAccess,
        protected WorkspaceAccess $access,
        protected BusinessCalendar $calendar,
        protected StageRequirements $requirements,
        protected PipelineChoice $pipelineChoice,
        protected FollowUpCutoffs $cutoffs,
    ) {}

    /**
     * The Opportunities page, each with its weighted value, in its currency
     * and the base one. The "stale" status (Phase D3a) lists the open ones
     * at a stage for longer than its stale-after period.
     */
    public function list(Workspace $workspace, User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $page = $this->opportunities->paginateFor($workspace, $this->filters($workspace, $user, $filters), $this->visibility->scopeFor($workspace, $user), $perPage);

        $this->mark($workspace, $page->items());

        return $page;
    }

    /**
     * $filters as the list reads them - and (Phase D4) the board and the
     * calendar: "me" for the owner is $user, and the "stale" status (Phase
     * D3a) gets each open stage's moment: one that entered it before is
     * stale. Phase G3b's "quiet" status gets the closing-soon days and the
     * moment since which it has had no activity (FollowUpCutoffs).
     */
    public function filters(Workspace $workspace, User $user, array $filters): array
    {
        if (($filters['owner_id'] ?? null) === 'me') {
            $filters['owner_id'] = $user->id;
        }

        if (($filters['status'] ?? null) === 'stale') {
            $filters['stale_since'] = $this->cutoffs->staleSince($workspace);
        }

        if (($filters['status'] ?? null) === 'quiet') {
            $filters = [...$filters, ...$this->cutoffs->quiet($workspace)];
        }

        return $filters;
    }

    /** Another business's opportunity - or one $user does not see - answers like one that does not exist. */
    public function find(Workspace $workspace, User $user, int $id): CrmOpportunity
    {
        return $this->opportunities->findFor($workspace, $id, $this->visibility->scopeFor($workspace, $user))
            ?? abort(404, 'Opportunity not found.');
    }

    /**
     * For its page: with its weighted values. They are set on the record, so
     * it is not saved after - a change reads it with find().
     */
    public function show(Workspace $workspace, User $user, int $id): CrmOpportunity
    {
        return $this->shown($workspace, $this->find($workspace, $user, $id));
    }

    /** One whose files are added or removed (Phase D2b): in any status, but not archived - restored first. */
    public function findForFiles(Workspace $workspace, User $user, int $id): CrmOpportunity
    {
        $opportunity = $this->find($workspace, $user, $id);

        if ($opportunity->isArchived()) {
            $this->refuse('An archived opportunity is restored before it changes.');
        }

        return $opportunity;
    }

    /** $opportunity with its weighted values, for its page - after its last save. */
    public function shown(Workspace $workspace, CrmOpportunity $opportunity): CrmOpportunity
    {
        $this->mark($workspace, [$opportunity]);

        return $opportunity;
    }

    /**
     * Their weighted values (OpportunityForecast), whether an open one's
     * closing day has passed - the business's today - and (Phase D3a) its
     * full days at its stage, and whether that is more than the stage's
     * stale-after period. The board's cards and the calendar (Phase D4) are
     * marked the same way.
     */
    public function mark(Workspace $workspace, array $opportunities): void
    {
        $this->forecast->attach($workspace, $opportunities);

        $today = $this->calendar->today($workspace)->toDateString();
        $now   = now();

        foreach ($opportunities as $opportunity) {
            $open    = $opportunity->status === CrmOpportunity::OPEN && ! $opportunity->isArchived();
            $seconds = $opportunity->stage_entered_at ? max(0, $opportunity->stage_entered_at->diffInSeconds($now)) : 0;
            $limit   = $opportunity->stage?->stale_after_days;

            $opportunity->setAttribute('overdue', $opportunity->status === CrmOpportunity::OPEN && $opportunity->expected_close_date?->toDateString() < $today);
            $opportunity->setAttribute('days_at_stage', (int) floor($seconds / 86400));
            $opportunity->setAttribute('stale', $open && $limit !== null && $seconds > $limit * 86400);
        }
    }

    /**
     * What an opportunity's form and the page's filters choose from: the
     * pipeline's open stages switched on, with their probabilities, the lead
     * sources and sales teams switched on, the people it can be given to,
     * and where a new one starts and who owns it - and (Phase D2a) the lost
     * reasons switched on, and (Phase G3b) the recent-activity days its
     * "quiet" filter is named with.
     */
    public function options(Workspace $workspace, User $user): array
    {
        $lists  = $this->options->getFor($workspace)->where('is_active', true);
        // Phase D3b: the pipelines switched on, each with its open stages - and every stage of theirs switched on, to filter by.
        $active = $this->pipelineChoice->active($workspace);

        return [
            'all_stages'   => $active->flatMap(fn (CrmPipeline $row) => $row->stages->where('is_active', true))->values(),
            'pipelines'    => $this->pipelineChoice->described($active),
            'sources'      => $lists->where('kind', 'lead_source')->values(),
            'lost_reasons' => $lists->where('kind', 'lost_reason')->values(),
            'teams'        => $this->teams->forWorkspace($workspace, true),
            'owners'       => $this->assignments->owners($workspace),
            'location_id'  => $this->locationAccess->defaultFor($workspace),
            'owner_id'     => $user->id,
            'recent_activity_days' => $this->settings->periods($workspace)['recent_activity_days'],
        ];
    }

    /** $data is validated (see SaveCrmOpportunityRequest); owner_id left out is the user. */
    public function create(Workspace $workspace, User $user, array $data): CrmOpportunity
    {
        $fields   = $this->checked($workspace, $data);
        // Phase D3b: the pipeline asked for - or the first that fits - for its sales team and location.
        $pipeline = $this->pipelineChoice->choose($workspace, isset($data['pipeline_id']) ? (int) $data['pipeline_id'] : null, $fields['sales_team_id'] ?? null, $fields['location_id'] ?? null);
        $stage    = $this->openStages($pipeline)->firstWhere('id', (int) $data['stage_id'])
            ?? throw ValidationException::withMessages(['stage_id' => "Choose one of the pipeline's open stages."]);
        $ownerId  = (int) ($data['owner_id'] ?? $user->id);
        $contact  = $this->contacts->findFor($workspace, (int) $fields['contact_id']);
        $items    = $this->interests->rows($workspace, $data['interested_items'] ?? []);

        // What its first stage needs filled in (Phase D3a).
        $this->requirements->ensure($stage, [...$fields, 'interested_items' => $items]);
        $this->assignments->ensureOwner($workspace, $ownerId, $fields['location_id']);

        $opportunity = DB::transaction(function () use ($workspace, $user, $fields, $pipeline, $stage, $ownerId, $contact, $items, $data) {
            $opportunity = $this->opportunities->create($workspace, [
                ...$fields,
                'pipeline_id'      => $pipeline->id,
                'stage_id'         => $stage->id,
                'status'           => CrmOpportunity::OPEN,
                'probability'      => $this->probability($data, $stage->probability),
                'owner_id'         => $ownerId,
                'stage_entered_at' => now(),
            ]);

            $this->opportunities->replaceItems($opportunity, $items);
            $this->opportunities->enterStage($opportunity, $stage->id, $user->id);
            $this->makeCustomer($contact, $opportunity);

            return $opportunity;
        });
        $opportunity = $this->opportunities->loadShown($opportunity);

        ActivityLogService::record('created', 'Added '.$this->describe($opportunity).' for contact "'.Str::limit($contact->name, 100).'"'
            .($ownerId !== $user->id ? " for {$opportunity->owner?->name}" : ''), $opportunity, ['source' => 'api']);

        $this->assignments->notify($workspace, $opportunity, $user);

        return $this->shown($workspace, $opportunity);
    }

    /** An open opportunity's fields, its owner among them; $data is validated (see SaveCrmOpportunityRequest). */
    public function update(Workspace $workspace, User $user, int $id, array $data): CrmOpportunity
    {
        $opportunity = $this->find($workspace, $user, $id);

        if (! $this->rules->allows($opportunity, 'edit')) {
            $this->refuse($opportunity->isArchived() ? 'An archived opportunity is restored before it is changed.' : 'A won or lost opportunity is reopened before it is changed.');
        }

        EditConflicts::ensureUnchanged($opportunity, 'this opportunity');

        $fields  = $this->checked($workspace, $data, $opportunity);
        $ownerId = isset($data['owner_id']) ? (int) $data['owner_id'] : $opportunity->owner_id;

        // Its owner may use its location: a new owner, or a new location. One whose owner's account is gone keeps none until given one.
        if ($ownerId !== null && ($ownerId !== $opportunity->owner_id || $fields['location_id'] !== $opportunity->location_id)) {
            $this->assignments->ensureOwner($workspace, $ownerId, $fields['location_id'], $ownerId !== $opportunity->owner_id ? 'owner_id' : 'location_id');
        }

        if (array_key_exists('probability', $data) && $data['probability'] !== null) {
            $fields['probability'] = $this->probability($data, $opportunity->probability);
        }

        $items        = array_key_exists('interested_items', $data) ? $this->interests->rows($workspace, $data['interested_items'] ?? []) : null;
        $itemsChanged = $items !== null && $this->interests->changed($opportunity, $items);
        $contact      = (int) $fields['contact_id'] !== $opportunity->contact_id ? $this->contacts->findFor($workspace, (int) $fields['contact_id']) : null;
        $gaveAway     = $ownerId !== $opportunity->owner_id;

        DB::transaction(function () use ($opportunity, $fields, $ownerId, $items, $itemsChanged, $contact) {
            $this->opportunities->update($opportunity, [...$fields, 'owner_id' => $ownerId]);

            // Its version moves with its items too, so another person's save of them is caught (EditConflicts).
            if ($itemsChanged) {
                $this->opportunities->replaceItems($opportunity, $items);
                $this->opportunities->touch($opportunity);
            }

            if ($contact) {
                $this->makeCustomer($contact, $opportunity);
            }
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($opportunity), $opportunity, [
            'source' => 'api',
            'fields' => [
                ...array_keys(array_diff_key($opportunity->getChanges(), ['updated_at' => true, 'updated_by' => true])),
                ...($itemsChanged ? ['interested_items'] : []),
            ],
        ]);

        $opportunity = $this->opportunities->loadShown($opportunity);

        if ($gaveAway) {
            ActivityLogService::record('updated', 'Gave '.$this->describe($opportunity)." to {$opportunity->owner?->name}", $opportunity, ['source' => 'api', 'owner_id' => $ownerId, 'changes' => []]);
            $this->assignments->notify($workspace, $opportunity, $user);
        }

        return $this->shown($workspace, $opportunity);
    }

    /** What the log calls it: 'opportunity "Uddin Traders - shelving"'. */
    public function describe(CrmOpportunity $opportunity): string
    {
        return 'opportunity "'.Str::limit($opportunity->name, 100).'"';
    }

    /**
     * The probability to keep: one given that is not $default needs Change
     * Probability (Doc 17 section 17.14) - else it is $default.
     */
    public function probability(array $data, int $default): int
    {
        $given = isset($data['probability']) ? (int) $data['probability'] : null;

        if ($given === null || $given === $default) {
            return $default;
        }

        if (! $this->access->can('crm.opportunities.probability')) {
            throw ValidationException::withMessages(['probability' => "The probability is the stage's: another needs Change Probability."]);
        }

        return $given;
    }

    /** @return Collection<int, CrmStage> its open stages switched on, in order */
    public function openStages(CrmPipeline $pipeline): Collection
    {
        return $pipeline->stages->filter(fn (CrmStage $stage) => $stage->is_active && $stage->category === CrmStage::OPEN)->values();
    }

    /**
     * $data's fields, with its contact one of the business's active ones not
     * merged into another, its source, team and location the business's own
     * and switched on - or the opportunity's already.
     */
    private function checked(Workspace $workspace, array $data, ?CrmOpportunity $opportunity = null): array
    {
        $fields = Arr::only($data, self::FIELDS);

        $contactId = (int) $fields['contact_id'];

        if ($contactId !== $opportunity?->contact_id) {
            $contact = $this->contacts->findFor($workspace, $contactId);

            if (! $contact || ! $contact->is_active || $contact->isMerged()) {
                throw ValidationException::withMessages(['contact_id' => 'Choose one of your active contacts.']);
            }
        }

        if (isset($fields['source_id']) && (int) $fields['source_id'] !== $opportunity?->source_id) {
            $source = $this->options->findFor($workspace, (int) $fields['source_id']);

            if (! $source || $source->kind !== 'lead_source' || ! $source->is_active) {
                throw ValidationException::withMessages(['source_id' => 'Choose one of the lead sources in Settings → CRM.']);
            }
        }

        $team = isset($fields['sales_team_id']) ? (int) $fields['sales_team_id'] : null;

        if ($team !== null && $team !== $opportunity?->sales_team_id && ! $this->teams->isActive($workspace, $team)) {
            throw ValidationException::withMessages(['sales_team_id' => 'Choose one of the sales teams switched on.']);
        }

        $fields['location_id'] = $this->location($workspace, isset($fields['location_id']) ? (int) $fields['location_id'] : null, $opportunity);

        return $fields;
    }

    /** Where it is: a location of the business, switched on and the user's to use - or its own - else where their new records start. */
    private function location(Workspace $workspace, ?int $id, ?CrmOpportunity $opportunity): ?int
    {
        if ($id === null) {
            return $opportunity?->location_id ?? $this->locationAccess->defaultFor($workspace);
        }

        if ($id === $opportunity?->location_id) {
            return $id;
        }

        $location = $this->locations->findFor($workspace, $id);

        if (! $location || ! $location->is_active || ! $this->access->usesLocation($id)) {
            throw ValidationException::withMessages(['location_id' => 'Choose one of your locations that is switched on.']);
        }

        return $id;
    }

    /** An opportunity is a possible sale: its contact is a customer - a supplier becomes both, as on converting a lead. */
    private function makeCustomer(Contact $contact, CrmOpportunity $opportunity): void
    {
        if ($contact->role !== Contact::ROLE_SUPPLIER) {
            return;
        }

        $this->contacts->update($contact, ['role' => Contact::ROLE_BOTH]);

        ActivityLogService::record('updated', 'Made contact "'.Str::limit($contact->name, 100).'" a customer as well, for '.$this->describe($opportunity), $contact, ['source' => 'api']);
    }
}
