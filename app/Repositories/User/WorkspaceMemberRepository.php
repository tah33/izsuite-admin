<?php

namespace App\Repositories\User;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Models\User\WorkspaceRole;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceMemberRepository
{
    /** What a member comes back with: role, account (last sign-in) and locations. */
    private const RELATIONS = ['role:id,name,template', 'user:id,email,email_verified_at,last_login_at', 'locations:id'];

    /** $user's membership of the business while it is active, with its role and locations. */
    public function activeFor(Workspace $workspace, User $user): ?WorkspaceMember
    {
        return $workspace->members()->with(['role', 'locations:id'])->where('user_id', $user->id)->where('status', WorkspaceMember::ACTIVE)->first();
    }

    /** $user as a member of the business under $role - made once, kept after. */
    public function firstOrCreateFor(Workspace $workspace, User $user, WorkspaceRole $role): WorkspaceMember
    {
        return $workspace->members()->firstOrCreate(['user_id' => $user->id], [
            'role_id'     => $role->id,
            'name'        => mb_substr($user->name, 0, 150),
            'email'       => mb_strtolower($user->email),
            'accepted_at' => now(),
        ]);
    }

    /** Every member, the owner and the active first, then by name - removed ones last. */
    public function getFor(Workspace $workspace): Collection
    {
        $order = array_flip(WorkspaceMember::STATUSES);

        return $workspace->members()->with(self::RELATIONS)->get()
            ->sortBy(fn (WorkspaceMember $member) => [
                $member->isOwnerOf($workspace) ? 0 : 1,
                $member->status === WorkspaceMember::ACTIVE ? 0 : 1 + $order[$member->status],
                mb_strtolower((string) $member->name),
            ])
            ->values();
    }

    public function findFor(Workspace $workspace, int $id): ?WorkspaceMember
    {
        return $workspace->members()->with(self::RELATIONS)->find($id);
    }

    /** Those a plan's user limit counts - active and invited - the owner first, then the active, by name (Doc 13 section 14). */
    public function countedFor(Workspace $workspace): Collection
    {
        return $workspace->members()->with('role:id,name')->whereIn('status', WorkspaceMember::COUNTED)->get()
            ->sortBy(fn (WorkspaceMember $member) => [
                $member->isOwnerOf($workspace) ? 0 : 1,
                $member->status === WorkspaceMember::ACTIVE ? 0 : 1,
                mb_strtolower((string) $member->name),
            ])
            ->values();
    }

    /** The business's member invited as $email - whatever their status. */
    public function findByEmail(Workspace $workspace, string $email): ?WorkspaceMember
    {
        return $workspace->members()->where('email', mb_strtolower($email))->first();
    }

    /** $user's membership of the business - whatever its status. */
    public function findForUser(Workspace $workspace, User $user): ?WorkspaceMember
    {
        return $workspace->members()->where('user_id', $user->id)->first();
    }

    /** The pending invitation whose link hashes to $hash, with its business, role and sender. */
    public function findInvitation(string $hash): ?WorkspaceMember
    {
        return WorkspaceMember::with(['workspace', 'role:id,name', 'inviter:id,first_name,last_name'])
            ->where('invitation_token', $hash)
            ->where('status', WorkspaceMember::PENDING)
            ->first();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): WorkspaceMember
    {
        return $workspace->members()->create($data);
    }

    public function update(WorkspaceMember $member, array $data): WorkspaceMember
    {
        $member->update($data);

        return $member;
    }

    /** The locations a member with selected access may use; none with all. */
    public function syncLocations(WorkspaceMember $member, array $locationIds): void
    {
        $member->locations()->sync($locationIds);
    }

    /** The members with Selected Locations - all but the removed - with the locations chosen for them. */
    public function withChosenLocations(Workspace $workspace): Collection
    {
        return $workspace->members()
            ->with('locations:id')
            ->where('location_access', WorkspaceMember::SELECTED_LOCATIONS)
            ->where('status', '!=', WorkspaceMember::REMOVED)
            ->orderBy('name')
            ->get();
    }

    /** Location $locationId among those $member may use ($on), or not. */
    public function toggleLocation(WorkspaceMember $member, int $locationId, bool $on): void
    {
        $on ? $member->locations()->syncWithoutDetaching([$locationId]) : $member->locations()->detach($locationId);
    }

    public function delete(WorkspaceMember $member): void
    {
        $member->delete();
    }

    /**
     * Writes $details - name, phone, language, time zone - onto $user's
     * record in every business they belong to; a removed one keeps what it
     * had. How many records changed.
     */
    public function updateDetailsOf(User $user, array $details): int
    {
        return WorkspaceMember::where('user_id', $user->id)->where('status', '!=', WorkspaceMember::REMOVED)->update($details);
    }

    /** Those who count toward the package's user limit: pending and active. */
    public function countCounted(Workspace $workspace): int
    {
        return $workspace->members()->whereIn('status', WorkspaceMember::COUNTED)->count();
    }

    /** Hands Business Owner to $member, with every location, and $fallback - Administrator - to the owner before. */
    public function swapOwner(WorkspaceMember $member, ?WorkspaceMember $previous, WorkspaceRole $owner, WorkspaceRole $fallback): void
    {
        $member->update(['role_id' => $owner->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        $previous?->update(['role_id' => $fallback->id]);
    }
}
