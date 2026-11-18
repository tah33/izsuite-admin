<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmLeadRules;
use App\Services\Crm\LeadAssignments;
use App\Services\Crm\LeadQualification;
use App\Services\Crm\LeadRouting;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A lead's steps (Doc 17 sections 17.7 and 17.9 - Phase B1), as CrmLeadRules
 * allows them:
 *
 * - Assign: to an active member who may view leads at its location. A New
 *   lead becomes Assigned; any other keeps its status. The log keeps the
 *   previous owner, and the new one is told. A lead in the queue can be
 *   sent through the assignment rules instead (Phase I1).
 * - Mark Contacted, Qualify - once what Settings > CRM requires is filled in
 *   (Phase B2a) - Unqualify (with a reason) and Archive (with one or not).
 * - Reopen: an unqualified or archived lead back to Assigned - New, without
 *   an owner.
 */
class CrmLeadFlow
{
    use RefusesConflicts;

    public function __construct(
        protected CrmLeadService $service,
        protected CrmLeadRepository $leads,
        protected CrmLeadRules $rules,
        protected LeadAssignments $assignments,
        protected LeadQualification $qualification,
        protected LeadRouting $routing,
    ) {}

    /** A lead in the queue given to whom the first fitting assignment rule says (Phase I1) - refused when none fits. */
    public function assignByRules(Workspace $workspace, User $user, int $id): CrmLead
    {
        $lead = $this->allowed($workspace, $user, $id, 'assign', 'An unqualified or archived lead is reopened before it is assigned.');

        if ($lead->owner_id !== null) {
            throw ValidationException::withMessages(['owner_id' => 'Only a lead in the queue goes to the assignment rules.']);
        }

        $routed = DB::transaction(function () use ($workspace, $lead) {
            $routed = $this->routing->route($workspace, $lead, $lead->interestedItems->pluck('item_id')->filter()->map(fn ($id) => (int) $id)->values()->all());

            if ($routed) {
                $now = now();

                $this->leads->update($lead, ['owner_id' => $routed['owner_id'], 'status' => CrmLead::ASSIGNED, 'assigned_at' => $now, 'first_assigned_at' => $lead->first_assigned_at ?? $now]);
            }

            return $routed;
        });

        if (! $routed) {
            throw ValidationException::withMessages(['owner_id' => 'No assignment rule fits this lead. Assign it by hand.']);
        }

        $lead = $this->leads->loadShown($lead);

        ActivityLogService::record('updated', 'Assigned '.$this->service->describe($lead)." to {$lead->owner->name} by the assignment rule \"{$routed['rule']->name}\"", $lead, [
            'source'            => 'api',
            'owner_id'          => $lead->owner_id,
            'previous_owner_id' => null,
            'rule_id'           => $routed['rule']->id,
        ]);

        $this->assignments->notify($workspace, $lead, $user);

        return $lead;
    }

    public function assign(Workspace $workspace, User $user, int $id, int $ownerId): CrmLead
    {
        $lead     = $this->allowed($workspace, $user, $id, 'assign', 'An unqualified or archived lead is reopened before it is assigned.');
        $previous = $lead->owner;

        if ($previous?->id === $ownerId) {
            throw ValidationException::withMessages(['owner_id' => "{$previous->name} has this lead already."]);
        }

        $this->assignments->ensureOwner($workspace, $ownerId, $lead->location_id);

        $now = now();

        $this->leads->update($lead, [
            'owner_id'          => $ownerId,
            'status'            => $lead->status === CrmLead::NEW ? CrmLead::ASSIGNED : $lead->status,
            'assigned_at'       => $now,
            // Phase G3a: the first owner's moment stays.
            'first_assigned_at' => $lead->first_assigned_at ?? $now,
        ]);
        $lead = $this->leads->loadShown($lead);

        ActivityLogService::record('updated', 'Assigned '.$this->service->describe($lead)." to {$lead->owner->name}".($previous ? " (was {$previous->name})" : ''), $lead, [
            'source'            => 'api',
            'owner_id'          => $ownerId,
            'previous_owner_id' => $previous?->id,
        ]);

        $this->assignments->notify($workspace, $lead, $user);

        return $lead;
    }

    public function contact(Workspace $workspace, User $user, int $id): CrmLead
    {
        $lead = $this->allowed($workspace, $user, $id, 'contact', 'An assigned lead is the one marked contacted.');
        $now  = now();

        // Phase G3a: its first contact stays, however often it is marked again.
        return $this->move($lead, ['status' => CrmLead::CONTACTED, 'contacted_at' => $now, 'first_contacted_at' => $lead->first_contacted_at ?? $now], 'Marked '.$this->service->describe($lead).' contacted');
    }

    /** Once what Settings > CRM requires (Phase B2a) is filled in. Nothing is sold by it (section 17.10). */
    public function qualify(Workspace $workspace, User $user, int $id): CrmLead
    {
        $lead    = $this->allowed($workspace, $user, $id, 'qualify', 'A lead with an owner, assigned or contacted, is the one qualified.');
        $missing = $this->qualification->missing($workspace, $lead);

        if ($missing !== []) {
            throw ValidationException::withMessages(['qualification' => 'Fill in '.$this->qualification->describe($missing).' before qualifying this lead.']);
        }

        return $this->move($lead, ['status' => CrmLead::QUALIFIED], 'Qualified '.$this->service->describe($lead));
    }

    public function unqualify(Workspace $workspace, User $user, int $id, string $reason): CrmLead
    {
        $lead = $this->allowed($workspace, $user, $id, 'unqualify', 'Only an open lead is marked unqualified.');

        return $this->move($lead, ['status' => CrmLead::UNQUALIFIED, 'status_reason' => $reason], 'Marked '.$this->service->describe($lead).' unqualified', ['reason' => $reason]);
    }

    public function archive(Workspace $workspace, User $user, int $id, ?string $reason): CrmLead
    {
        $lead = $this->allowed($workspace, $user, $id, 'archive', 'This lead cannot be archived.');

        return $this->move($lead, ['status' => CrmLead::ARCHIVED, 'status_reason' => $reason], 'Archived '.$this->service->describe($lead), ['reason' => $reason]);
    }

    public function reopen(Workspace $workspace, User $user, int $id): CrmLead
    {
        $lead = $this->allowed($workspace, $user, $id, 'reopen', 'Only an unqualified or archived lead is reopened.');

        return $this->move($lead, ['status' => $lead->owner_id ? CrmLead::ASSIGNED : CrmLead::NEW, 'status_reason' => null], 'Reopened '.$this->service->describe($lead));
    }

    /** $lead - one $user sees - when its status allows $action. */
    private function allowed(Workspace $workspace, User $user, int $id, string $action, string $refusal): CrmLead
    {
        $lead = $this->service->find($workspace, $user, $id);

        if (! $this->rules->allows($lead, $action)) {
            $this->refuse($refusal);
        }

        return $lead;
    }

    private function move(CrmLead $lead, array $changes, string $what, array $properties = []): CrmLead
    {
        $this->leads->update($lead, $changes);

        ActivityLogService::record('updated', $what, $lead, ['source' => 'api', 'status' => $changes['status'], ...array_filter($properties)]);

        return $this->leads->loadShown($lead);
    }
}
