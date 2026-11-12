<?php

namespace App\Services\Shared\Notifications;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;

/**
 * Who a notification may reach (Doc 14 section 15 - Phase B1): the
 * business's active users allowed to see its record - holding the
 * permission, and with access to its location when it has one. The owner
 * holds every permission and every location.
 */
class NotificationAudience
{
    public function __construct(
        private WorkspaceMemberRepository $members,
        private WorkspaceAccess $access,
    ) {}

    /** @return Collection<int, User> */
    public function withPermission(Workspace $workspace, string $permission, ?int $locationId = null): Collection
    {
        return $this->membersWithPermission($workspace, $permission, $locationId)
            ->map(fn (WorkspaceMember $member) => $member->user)
            ->values();
    }

    /**
     * As withPermission(), their memberships, each with its user. This is
     * for their names in the business, as a mention picks them (Doc 14
     * Phase D).
     *
     * @return Collection<int, WorkspaceMember>
     */
    public function membersWithPermission(Workspace $workspace, string $permission, ?int $locationId = null): Collection
    {
        return $this->members->getFor($workspace)
            ->filter(fn (WorkspaceMember $member) => $member->status === WorkspaceMember::ACTIVE && $member->user)
            ->filter(fn (WorkspaceMember $member) => $this->access->allows($member->user, $workspace, $permission) && $this->reaches($member->user, $workspace, $locationId))
            ->values();
    }

    private function reaches(User $user, Workspace $workspace, ?int $locationId): bool
    {
        if ($locationId === null) {
            return true;
        }

        $locations = $this->access->locationsOf($user, $workspace);

        return $locations === null || in_array($locationId, $locations, true);
    }
}
