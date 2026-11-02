<?php

namespace App\Services\Api\Members;

use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Models\User\WorkspaceRole;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\UserRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Repositories\User\WorkspaceRoleRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\PlanLimits;
use App\Support\Csv;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings > Users & Roles > Users (Doc 12 sections 3 to 8 and 20): the
 * business's members - its owner first - invited, changed, and their
 * invitations sent again or cancelled. Suspending, removing and handing over
 * the business are MemberStatusService's.
 *
 * An invitation goes to an e-mail address once per business: someone invited,
 * or a member, cannot be invited twice; someone removed is invited again on
 * the same row. Adding a user waits for the package to have room (section 3).
 * A member holds one active role - never Business Owner, which only moves with
 * the business - and all of the business's locations or those chosen, one of
 * them the default. Nobody changes their own role or locations (section 22).
 */
class MemberService
{
    use RefusesConflicts;

    public function __construct(
        protected WorkspaceMemberRepository $memberRepository,
        protected WorkspaceRoleRepository $roleRepository,
        protected LocationRepository $locationRepository,
        protected UserRepository $userRepository,
        protected PlanLimits $planLimits,
        protected InvitationSender $invitations,
        protected WorkspaceRepository $workspaceRepository,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->memberRepository->getFor($workspace);
    }

    public function show(Workspace $workspace, int $id): WorkspaceMember
    {
        return $this->find($workspace, $id);
    }

    /**
     * $data is validated (see InviteMemberRequest). Saved even when the e-mail
     * cannot go - `sent` says, and it is sent again from the list.
     *
     * @return array{member: WorkspaceMember, sent: bool}
     */
    public function invite(Workspace $workspace, User $inviter, array $data): array
    {
        $email    = mb_strtolower(trim($data['email']));
        $account  = $this->userRepository->findByEmail($email);
        $existing = $this->memberRepository->findByEmail($workspace, $email)
            ?? ($account ? $this->memberRepository->findForUser($workspace, $account) : null);

        if ($existing && $existing->status !== WorkspaceMember::REMOVED) {
            throw ValidationException::withMessages(['email' => $existing->isPending()
                ? 'This e-mail is invited already. Send the invitation again from the list instead.'
                : 'This person is a user of the business already.']);
        }

        $this->planLimits->ensureCanAddUser($workspace);

        $role                 = $this->role($workspace, (int) $data['role_id']);
        [$access, $locations] = $this->access($workspace, $data);
        [$secret, $link]      = $this->invitations->issue($inviter);

        $member = DB::transaction(function () use ($workspace, $existing, $data, $email, $role, $access, $locations, $link) {
            $fields = [
                ...$this->profile($data),
                'email'               => $email,
                'role_id'             => $role->id,
                ...$access,
                'status'              => WorkspaceMember::PENDING,
                ...$link,
                'accepted_at'         => null,
                'suspended_at'        => null,
                'removed_at'          => null,
            ];

            $member = $existing ? $this->memberRepository->update($existing, $fields) : $this->memberRepository->create($workspace, $fields);
            $this->memberRepository->syncLocations($member, $locations);

            return $member;
        });

        $member = $this->find($workspace, $member->id);
        $sent   = $this->invitations->send($workspace, $member, $inviter, $secret);

        ActivityLogService::record('invited', "Invited {$this->quoted($member)} ({$email}) as {$role->name}", $member, ['source' => 'api', 'emailed' => $sent]);

        return ['member' => $member, 'sent' => $sent];
    }

    /** $data is validated (see UpdateMemberRequest). The owner keeps Business Owner and every location. */
    public function update(Workspace $workspace, User $actor, int $id, array $data): WorkspaceMember
    {
        $member = $this->find($workspace, $id);

        if ($member->status === WorkspaceMember::REMOVED) {
            $this->refuse('A removed user is invited again rather than changed.');
        }

        $before = $this->state($workspace, $member);
        $fields = $this->profile($data);

        if (! $member->isOwnerOf($workspace)) {
            $role                 = $this->role($workspace, (int) $data['role_id']);
            [$access, $locations] = $this->access($workspace, $data);

            if ($member->user_id === $actor->id && ($role->id !== $member->role_id || $this->locationsChange($member, $access, $locations))) {
                abort(403, 'You cannot change your own role or locations.');
            }

            $fields = [...$fields, 'role_id' => $role->id, ...$access];
        } else {
            $locations = [];
            $fields    = [...$fields, 'default_location_id' => $this->defaultLocation($workspace, $data['default_location_id'] ?? null, null)];
        }

        DB::transaction(function () use ($member, $fields, $locations) {
            $this->memberRepository->update($member, $fields);
            $this->memberRepository->syncLocations($member, $locations);
        });

        // Their sidebar carries their permissions and locations: built afresh.
        $this->workspaceRepository->bumpNavVersion($workspace);

        $member = $this->find($workspace, $member->id);

        ActivityLogService::record('updated', "Updated user {$this->quoted($member)}", $member, array_filter([
            'source'  => 'api',
            'changes' => SettingChanges::between($before, $this->state($workspace, $member)),
        ]));

        return $member;
    }

    /**
     * Sends a pending invitation again, with a new link: the old one stops
     * working. It also sends one never sent - staff from before Users.
     *
     * @return array{member: WorkspaceMember, sent: bool}
     */
    public function resend(Workspace $workspace, User $inviter, int $id): array
    {
        $member = $this->find($workspace, $id);

        if (! $member->isPending()) {
            $this->refuse('Only a pending invitation can be sent again.');
        }

        [$secret, $link] = $this->invitations->issue($inviter);

        $this->memberRepository->update($member, $link);

        $sent = $this->invitations->send($workspace, $member, $inviter, $secret);

        ActivityLogService::record('invited', "Sent the invitation to {$this->quoted($member)} again", $member, ['source' => 'api', 'emailed' => $sent]);

        return ['member' => $this->find($workspace, $member->id), 'sent' => $sent];
    }

    /** Withdraws a pending invitation: its link stops working. Someone removed before stays removed. */
    public function cancel(Workspace $workspace, int $id): void
    {
        $member = $this->find($workspace, $id);

        if (! $member->isPending()) {
            $this->refuse('Only a pending invitation can be cancelled. Remove the user instead.');
        }

        if ($member->user_id) {
            $this->memberRepository->update($member, ['status' => WorkspaceMember::REMOVED, 'invitation_token' => null, 'invitation_expires_at' => null]);
        } else {
            $this->memberRepository->delete($member);
        }

        ActivityLogService::record('cancelled', "Cancelled the invitation to {$this->quoted($member)}", $member, ['source' => 'api']);
    }

    /** The list as a CSV file (Doc 12 section 8, "Export User List"). */
    public function export(Workspace $workspace): StreamedResponse
    {
        $locations = $this->locationRepository->getFor($workspace)->pluck('name', 'id');

        $rows = $this->memberRepository->getFor($workspace)->map(fn (WorkspaceMember $member) => [
            $member->name,
            $member->email,
            $member->role?->name,
            Str::headline($member->status),
            $member->location_access === WorkspaceMember::ALL_LOCATIONS ? 'All locations' : $member->locations->map(fn (Location $location) => $locations[$location->id] ?? '')->filter()->join('; '),
            $locations[$member->default_location_id] ?? '',
            $member->user?->last_login_at?->toDateTimeString() ?? '',
        ]);

        ActivityLogService::record('exported', 'Exported the user list', $workspace, ['source' => 'api']);

        return Csv::download('users-'.now()->toDateString().'.csv', ['Name', 'Email', 'Role', 'Status', 'Location Access', 'Default Location', 'Last Login'], $rows);
    }

    /** An active role of the business - never Business Owner, which only moves with the business. */
    private function role(Workspace $workspace, int $id): WorkspaceRole
    {
        $role = $this->roleRepository->findFor($workspace, $id);

        if (! $role || ! $role->is_active || $role->isOwner()) {
            throw ValidationException::withMessages(['role_id' => 'Choose one of the business\'s active roles. Business Owner moves only with the business.']);
        }

        return $role;
    }

    /**
     * Where the member may work (Doc 12 section 20): every location, or those
     * listed - active ones of the business - with the default among them.
     *
     * @return array{0: array{location_access: string, default_location_id: int|null}, 1: list<int>}
     */
    private function access(Workspace $workspace, array $data): array
    {
        $all = ($data['location_access'] ?? WorkspaceMember::ALL_LOCATIONS) === WorkspaceMember::ALL_LOCATIONS;
        $ids = $all ? [] : array_values(array_unique(array_map('intval', $data['location_ids'] ?? [])));

        if (! $all) {
            $active = $this->activeLocationIds($workspace);

            if (! $ids || array_diff($ids, $active)) {
                throw ValidationException::withMessages(['location_ids' => 'Choose at least one of the business\'s active locations.']);
            }
        }

        return [[
            'location_access'     => $all ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
            'default_location_id' => $this->defaultLocation($workspace, $data['default_location_id'] ?? null, $all ? null : $ids),
        ], $ids];
    }

    /** One of $allowed - else of the business's active locations - the Primary when none is given. */
    private function defaultLocation(Workspace $workspace, mixed $id, ?array $allowed): ?int
    {
        $allowed ??= $this->activeLocationIds($workspace);

        if ($id === null || $id === '') {
            $primary = $this->locationRepository->primaryFor($workspace)?->id;

            return in_array($primary, $allowed, true) ? $primary : ($allowed[0] ?? null);
        }

        if (! in_array((int) $id, $allowed, true)) {
            throw ValidationException::withMessages(['default_location_id' => 'The default location must be one the user may use.']);
        }

        return (int) $id;
    }

    /** @return list<int> */
    private function activeLocationIds(Workspace $workspace): array
    {
        return $this->locationRepository->getFor($workspace)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    private function locationsChange(WorkspaceMember $member, array $access, array $locations): bool
    {
        $held = $member->locations->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $access['location_access'] !== $member->location_access || $held !== collect($locations)->sort()->values()->all();
    }

    private function profile(array $data): array
    {
        return [
            'name'      => trim($data['name']),
            'phone'     => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'language'  => $data['language'] ?? null,
            'timezone'  => $data['timezone'] ?? null,
        ];
    }

    /** What the Activity Log compares (Doc 12 section 24: role change, location assignment). */
    private function state(Workspace $workspace, WorkspaceMember $member): array
    {
        $names = $this->locationRepository->namesFor($workspace, array_filter([...$member->locations->pluck('id')->all(), $member->default_location_id]));

        return [
            'name'             => $member->name,
            'phone'            => $member->phone,
            'job_title'        => $member->job_title,
            'language'         => $member->language,
            'timezone'         => $member->timezone,
            'role'             => $member->role?->name,
            'location_access'  => $member->location_access,
            'locations'        => $member->locations->map(fn (Location $location) => $names[$location->id] ?? '')->filter()->sort()->values()->all(),
            'default_location' => $names[$member->default_location_id] ?? null,
        ];
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
