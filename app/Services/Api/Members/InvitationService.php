<?php

namespace App\Services\Api\Members;

use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Services\Api\Auth\AuthService;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * Accepting an invitation to a business (Doc 12 sections 4 and 6): the link
 * in the e-mail shows who invited whom to what, then joins the business -
 * with the account of that e-mail, signed in, or a new one made on the spot.
 * Until then the invitee has no access to the business at all.
 *
 * A link works once, until it expires; sent again, only the newest works.
 */
class InvitationService
{
    use RefusesConflicts;

    public function __construct(
        protected WorkspaceMemberRepository $memberRepository,
        protected UserRepository $userRepository,
        protected AuthService $authService,
    ) {}

    /**
     * The invitation behind $secret, and whether its e-mail has an account
     * already - to sign in with, rather than make one.
     *
     * @return array{member: WorkspaceMember, account_exists: bool}
     */
    public function show(string $secret): array
    {
        $member = $this->find($secret);

        return ['member' => $member, 'account_exists' => $this->userRepository->emailExists($member->email)];
    }

    /** Joins with the account signed in - the one the invitation was sent to. */
    public function accept(string $secret, User $user): WorkspaceMember
    {
        $member = $this->find($secret);

        abort_unless(mb_strtolower($user->email) === $member->email, 403, "This invitation is for {$member->email}. Sign in with that account to accept it.");

        return $this->join($member, $user);
    }

    /**
     * A new account for the invitation's e-mail, joined and signed in. $data
     * is validated (see RegisterFromInvitationRequest).
     *
     * @return array{user: User, token: NewAccessToken, member: WorkspaceMember}
     */
    public function register(string $secret, array $data): array
    {
        $member = $this->find($secret);

        if ($this->userRepository->emailExists($member->email)) {
            $this->refuse('An account with this e-mail exists already. Sign in to accept the invitation.');
        }

        return DB::transaction(function () use ($member, $data) {
            $session = $this->authService->registerInvited($member->email, [...$data, 'timezone' => $member->timezone]);

            return [...$session, 'member' => $this->join($member, $session['user'])];
        });
    }

    private function join(WorkspaceMember $member, User $user): WorkspaceMember
    {
        $held = $this->memberRepository->findForUser($member->workspace, $user);

        if ($held && $held->id !== $member->id) {
            $this->refuse('You are a user of this business already.');
        }

        $this->memberRepository->update($member, [
            'user_id'               => $user->id,
            'status'                => WorkspaceMember::ACTIVE,
            'accepted_at'           => now(),
            'invitation_token'      => null,
            'invitation_expires_at' => null,
        ]);

        ActivityLogService::record('accepted', "Accepted the invitation to \"{$member->workspace->name}\"", $member, ['source' => 'api'], $user);

        return $member;
    }

    /** A pending invitation whose link still works. */
    private function find(string $secret): WorkspaceMember
    {
        $member = $this->memberRepository->findInvitation(InvitationSender::hash($secret))
            ?? abort(404, 'This invitation is no longer valid. Ask the business to send you a new one.');

        abort_unless($member->invitation_expires_at?->isFuture(), 410, 'This invitation has expired. Ask the business to send it again.');

        return $member;
    }
}
