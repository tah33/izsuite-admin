<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notifier;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Validation\ValidationException;

/**
 * Who a lead can be given to, and telling them (Doc 17 section 17.9 - Phase
 * B1): an active member who may view leads and use the lead's location. The
 * new owner hears of it in the bell and by e-mail, under the Leads kind -
 * unless they gave it to themselves.
 */
class LeadAssignments
{
    public function __construct(
        protected NotificationAudience $audience,
        protected Notifier $notifier,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * The people a lead can be given to, by name, each with the locations
     * they may use - null for every one.
     *
     * @return list<array{id: int, name: string, locations: list<int>|null}>
     */
    public function owners(Workspace $workspace): array
    {
        $people = $this->audience->membersWithPermission($workspace, 'crm.leads.view')
            ->map(fn (WorkspaceMember $member) => [
                'id'        => (int) $member->user_id,
                'name'      => $member->user->name,
                'locations' => $this->access->locationsOf($member->user, $workspace),
            ]);

        // The owner, everywhere, whether or not a membership stands for them.
        if ($workspace->user && ! $people->contains('id', (int) $workspace->user_id)) {
            $people->push(['id' => (int) $workspace->user_id, 'name' => $workspace->user->name, 'locations' => null]);
        }

        return $people->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** Refuses $ownerId unless they are one of owners() who may use $locationId. */
    public function ensureOwner(Workspace $workspace, int $ownerId, ?int $locationId, string $field = 'owner_id'): void
    {
        $owner = collect($this->owners($workspace))->firstWhere('id', $ownerId);

        if (! $owner || ($locationId !== null && $owner['locations'] !== null && ! in_array($locationId, $owner['locations'], true))) {
            throw ValidationException::withMessages([$field => $field === 'owner_id'
                ? "Choose an active member who may view leads at this lead's location."
                : "The lead's owner may not use that location. Give the lead to someone who may first."]);
        }
    }

    /** Tells $lead's owner that $by gave it to them - nobody, when they took it themselves. */
    public function notify(Workspace $workspace, CrmLead $lead, User $by): void
    {
        if (! $lead->owner || $lead->owner->id === $by->id) {
            return;
        }

        $this->notifier->send($workspace, [$lead->owner], new Notice(
            trigger: 'leads.assigned',
            key: "leads.assigned:{$lead->id}:{$lead->owner->id}:".$lead->assigned_at?->getTimestamp(),
            title: "{$by->name} gave you a lead: {$lead->name}",
            body: collect([$lead->business_name, $lead->source?->name ? "from {$lead->source->name}" : null])->filter()->implode(', ') ?: null,
            link: "/crm/leads/{$lead->id}",
            subject: $lead,
            email: true,
        ), $by);
    }
}
