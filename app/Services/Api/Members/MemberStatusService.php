<?php

namespace App\Services\Api\Members;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Repositories\User\WorkspaceRoleRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * What happens to a user of the business (Doc 12 sections 7 to 9 and 23):
 * suspended - blocked at once, records and history kept, let back in later;
 * removed - membership revoked, invited again to return; signed out of every
 * session; and the business handed to another user.
 *
 * Nobody does these to themselves, nor to the Business Owner: the owner hands
 * the business over first, to an active user with a verified e-mail, after
 * confirming with their password. The previous owner stays, as Administrator.
 */
class MemberStatusService
{
    use RefusesConflicts;

    public function __construct(
        protected WorkspaceMemberRepository $memberRepository,
        protected WorkspaceRoleRepository $roleRepository,
        protected WorkspaceRepository $workspaceRepository,
        protected UserRepository $userRepository,
        protected PlanLimits $planLimits,
    ) {}

    public function suspend(Workspace $workspace, User $actor, int $id): WorkspaceMember
    {
        $member = $this->other($workspace, $actor, $id, 'suspended');

        if ($member->status !== WorkspaceMember::ACTIVE) {
            $this->refuse('Only an active user can be suspended.');
        }

        $this->memberRepository->update($member, ['status' => WorkspaceMember::SUSPENDED, 'suspended_at' => now()]);

        ActivityLogService::record('suspended', "Suspended user {$this->quoted($member)}", $member, ['source' => 'api']);

        return $this->find($workspace, $id);
    }

    /** A suspended user counts toward the package again, so it needs the room. */
    public function reactivate(Workspace $workspace, User $actor, int $id): WorkspaceMember
    {
        $member = $this->other($workspace, $actor, $id, 'reactivated');

        if ($member->status !== WorkspaceMember::SUSPENDED) {
            $this->refuse('Only a suspended user can be reactivated. Invite a removed one again.');
        }

        $this->planLimits->ensureCanAddUser($workspace);
        $this->memberRepository->update($member, ['status' => WorkspaceMember::ACTIVE, 'suspended_at' => null]);

        ActivityLogService::record('reactivated', "Reactivated user {$this->quoted($member)}", $member, ['source' => 'api']);

        return $this->find($workspace, $id);
    }

    public function remove(Workspace $workspace, User $actor, int $id): WorkspaceMember
    {
        $member = $this->other($workspace, $actor, $id, 'removed');

        if (! in_array($member->status, [WorkspaceMember::ACTIVE, WorkspaceMember::SUSPENDED], true)) {
            $this->refuse($member->isPending() ? 'Cancel a pending invitation instead.' : 'This user was removed already.');
        }

        $this->memberRepository->update($member, ['status' => WorkspaceMember::REMOVED, 'removed_at' => now(), 'suspended_at' => null]);

        ActivityLogService::record('removed', "Removed user {$this->quoted($member)}", $member, ['source' => 'api']);

        return $this->find($workspace, $id);
    }

    /** Every session of the user's account ends (Doc 12 section 8) - its other businesses' too: a session is the account's. */
    public function signOut(Workspace $workspace, User $actor, int $id): int
    {
        $member = $this->find($workspace, $id);

        if ($member->user_id === $actor->id) {
            $this->refuse('Sign yourself out from your own account menu.');
        }

        if (! $member->user) {
            $this->refuse('This user has not signed in yet.');
        }

        $ended = $this->userRepository->revokeTokens($member->user);

        ActivityLogService::record('signed_out', "Signed user {$this->quoted($member)} out of every session", $member, ['source' => 'api', 'revoked_tokens' => $ended]);

        return $ended;
    }

    /** Doc 12 section 9: only by the owner, to an active user with a verified e-mail, confirmed by password. */
    public function transferOwnership(Workspace $workspace, User $actor, int $id, string $password): WorkspaceMember
    {
        abort_unless($workspace->isOwnedBy($actor), 403, 'Only the Business Owner can hand the business over.');

        if (! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages(['password' => 'The password is not right.']);
        }

        $member = $this->find($workspace, $id);

        if ($member->isOwnerOf($workspace)) {
            $this->refuse('This user owns the business already.');
        }

        if ($member->status !== WorkspaceMember::ACTIVE || ! $member->user?->email_verified_at) {
            $this->refuse('The business can only go to an active user who has verified their e-mail.');
        }

        $this->planLimits->ensureCanTakeOver($member->user, $workspace);

        $owner    = $this->roleRepository->ownerRoleOf($workspace);
        $fallback = $this->roleRepository->templateOf($workspace, 'administrator') ?? $owner;
        $previous = $this->memberRepository->findForUser($workspace, $actor);

        DB::transaction(function () use ($workspace, $member, $previous, $owner, $fallback) {
            $this->workspaceRepository->reassignOwner($workspace, $member->user);
            $this->memberRepository->swapOwner($member, $previous, $owner, $fallback);
            $this->memberRepository->syncLocations($member, []);
        });

        $this->workspaceRepository->bumpNavVersion($workspace);

        ActivityLogService::record('transferred', "Handed the business over to {$this->quoted($member)}", $workspace, ['source' => 'api', 'to_user_id' => $member->user_id]);

        return $this->find($workspace, $id);
    }

    /** A user other than $actor, and not the owner - who cannot be $what. */
    private function other(Workspace $workspace, User $actor, int $id, string $what): WorkspaceMember
    {
        $member = $this->find($workspace, $id);

        if ($member->isOwnerOf($workspace)) {
            $this->refuse("The Business Owner cannot be {$what}. Hand the business over to another user first.");
        }

        if ($member->user_id === $actor->id) {
            $this->refuse("You cannot be {$what} by yourself. Ask another user who manages users.");
        }

        return $member;
    }

    /** Another business's member answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): WorkspaceMember
    {
        return $this->memberRepository->findFor($workspace, $id) ?? abort(404, 'User not found.');
    }

    private function quoted(WorkspaceMember $member): string
    {
        return '"'.Str::limit((string) $member->name, 50).'"';
    }
}
