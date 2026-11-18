<?php

namespace App\Services\Crm;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Validation\ValidationException;

/**
 * Who an activity can be given to (Doc 17 section 17.17 - Phase E1), as an
 * opportunity's owner is chosen (see OpportunityAssignments): an active
 * member who may view activities and use its record's location, or the
 * business's owner.
 */
class ActivityAssignments
{
    public function __construct(
        protected NotificationAudience $audience,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * The people an activity can be given to, by name, each with the
     * locations they may use - null for every one.
     *
     * @return list<array{id: int, name: string, locations: list<int>|null}>
     */
    public function assignees(Workspace $workspace): array
    {
        $people = $this->audience->membersWithPermission($workspace, 'crm.activities.view')
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

    /** Refused under $field unless $userId is one of assignees() who may use $locationId (none: any). */
    public function ensureAssignee(Workspace $workspace, int $userId, ?int $locationId, string $field = 'assigned_to'): void
    {
        $person = collect($this->assignees($workspace))->firstWhere('id', $userId);

        if (! $person || ($locationId !== null && $person['locations'] !== null && ! in_array($locationId, $person['locations'], true))) {
            throw ValidationException::withMessages([$field => "Choose an active member who may view activities at its record's location."]);
        }
    }
}
