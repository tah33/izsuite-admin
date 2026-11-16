<?php

namespace App\Services\Crm;

use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notifier;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Validation\ValidationException;

/**
 * Who an opportunity can be given to, and telling them (Doc 17 section 17.15
 * - Phase D1), as leads are (see LeadAssignments): an active member who may
 * view opportunities and use its location, or the business's owner. The new
 * owner hears of it in the bell and by e-mail, under the Opportunities kind -
 * unless they gave it to themselves.
 */
class OpportunityAssignments
{
    public function __construct(
        protected NotificationAudience $audience,
        protected Notifier $notifier,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * The people an opportunity can be given to, by name, each with the
     * locations they may use - null for every one.
     *
     * @return list<array{id: int, name: string, locations: list<int>|null}>
     */
    public function owners(Workspace $workspace): array
    {
        $people = $this->audience->membersWithPermission($workspace, 'crm.opportunities.view')
            ->map(fn (WorkspaceMember $member) => [
                'id'        => (int) $member->user_id,
                'name'      => $member->user->name,
                'locations' => $this->access->locationsOf($member->user, $workspace),
            ]);

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
                ? "Choose an active member who may view opportunities at this opportunity's location."
                : "The opportunity's owner may not use that location. Give it to someone who may first."]);
        }
    }

    /** Tells $opportunity's owner that $by gave it to them - nobody, when it is their own. */
    public function notify(Workspace $workspace, CrmOpportunity $opportunity, User $by): void
    {
        if (! $opportunity->owner || $opportunity->owner->id === $by->id) {
            return;
        }

        $this->notifier->send($workspace, [$opportunity->owner], new Notice(
            trigger: 'opportunities.assigned',
            key: "opportunities.assigned:{$opportunity->id}:{$opportunity->owner->id}:".$opportunity->updated_at?->getTimestamp(),
            title: "{$by->name} gave you an opportunity: {$opportunity->name}",
            body: $opportunity->contact?->name,
            link: "/crm/opportunities/{$opportunity->id}",
            subject: $opportunity,
            email: true,
        ), $by);
    }
}
