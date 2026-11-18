<?php

namespace App\Services\Api\Billing;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Api\Locations\LocationService;
use App\Services\Api\Members\MemberService;
use App\Services\Api\Members\MemberStatusService;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\PlanLimits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business over its plan's user or location limit - after a move to a
 * lower plan - brought back within it (Doc 13 section 14): its Business
 * Owner chooses the users and the locations that stay active. The other
 * users are suspended and their pending invitations withdrawn; the other
 * locations become inactive. Nothing is deleted: a suspended user can be
 * reactivated, and a location turned on again, once the plan has room, and
 * an inactive location's records stay readable.
 *
 * Until the owner chooses, nothing is switched off; only adding more is
 * refused (PlanLimits). The owner always stays, and so does the Primary
 * Location.
 */
class OverLimitService
{
    use RefusesConflicts;

    public function __construct(
        protected PlanLimits $planLimits,
        protected WorkspaceRepository $workspaceRepository,
        protected WorkspaceMemberRepository $memberRepository,
        protected LocationRepository $locationRepository,
        protected MemberStatusService $memberStatusService,
        protected MemberService $memberService,
        protected LocationService $locationService,
    ) {}

    /**
     * What is over a limit, to choose from: the users - active and invited,
     * the owner first - and the active locations, the Primary first, each
     * with its limit. A limit not passed comes as null.
     *
     * @return array{package: ?string, users: ?array{limit: int, members: Collection}, locations: ?array{limit: int, locations: Collection}}
     */
    public function choices(Workspace $workspace, User $actor): array
    {
        abort_unless($workspace->isOwnedBy($actor), 403, 'Only the Business Owner chooses what stays active.');

        $usage = $this->planLimits->usage($workspace);

        return [
            'package'   => $this->workspaceRepository->packageOf($workspace)?->name,
            'users'     => $usage['users']['state'] === 'over'
                ? ['limit' => $usage['users']['limit'], 'members' => $this->memberRepository->countedFor($workspace)]
                : null,
            'locations' => $usage['locations']['state'] === 'over'
                ? ['limit' => $usage['locations']['limit'], 'locations' => $this->locationRepository->activeFor($workspace)]
                : null,
        ];
    }

    /**
     * Keeps the owner and $memberIds, the Primary and $locationIds, active;
     * suspends the other users, withdraws the other invitations and turns the
     * other locations off - each logged as it would be done by hand, then the
     * whole in one entry. Answers with what was done, as a sentence, and the
     * business's usage now.
     *
     * @return array{message: string, usage: array}
     */
    public function keep(Workspace $workspace, User $actor, array $memberIds, array $locationIds): array
    {
        $choices   = $this->choices($workspace, $actor);

        if (! $choices['users'] && ! $choices['locations']) {
            $this->refuse('Your business is within its plan\'s limits, so there is nothing to choose.');
        }

        $leaving   = $choices['users'] ? $this->leaving($workspace, $choices['users'], array_map('intval', $memberIds)) : collect();
        $closing   = $choices['locations'] ? $this->closing($choices['locations'], array_map('intval', $locationIds)) : collect();
        $suspended = $leaving->where('status', WorkspaceMember::ACTIVE)->count();
        $withdrawn = $leaving->count() - $suspended;

        DB::transaction(function () use ($workspace, $actor, $leaving, $closing) {
            foreach ($leaving as $member) {
                $member->status === WorkspaceMember::ACTIVE
                    ? $this->memberStatusService->suspend($workspace, $actor, $member->id)
                    : $this->memberService->cancel($workspace, $member->id);
            }

            foreach ($closing as $location) {
                $this->locationService->deactivate($workspace, $location->id);
            }
        });

        $done      = array_filter([
            $suspended ? trans_choice('suspended :count user|suspended :count users', $suspended) : null,
            $withdrawn ? trans_choice('withdrew :count invitation|withdrew :count invitations', $withdrawn) : null,
            $closing->count() ? trans_choice('made :count location inactive|made :count locations inactive', $closing->count()) : null,
        ]);

        $sentence  = "Kept the business within {$choices['package']}".($done ? ': '.implode(', ', $done) : '');

        ActivityLogService::record('updated', $sentence, $workspace, ['source' => 'api']);

        return ['message' => "{$sentence}.", 'usage' => $this->planLimits->usage($workspace)];
    }

    /** The users not kept: every counted one but the owner and those chosen - no more than the limit stay. */
    private function leaving(Workspace $workspace, array $users, array $chosen): Collection
    {
        $members = $users['members'];
        $unknown = array_diff($chosen, $members->pluck('id')->all());

        if ($unknown) {
            throw ValidationException::withMessages(['member_ids' => 'Choose from the users listed.']);
        }

        $others  = $members->reject(fn (WorkspaceMember $member) => $member->isOwnerOf($workspace));
        $kept    = $others->whereIn('id', $chosen);

        // The owner always stays, and takes one of the places.
        if ($kept->count() + 1 > $users['limit']) {
            $room = $users['limit'] - 1;

            throw ValidationException::withMessages(['member_ids' => match ($room) {
                0       => 'Your plan includes one user - you, the owner - so nobody else can stay active.',
                1       => 'Your plan includes 2 users, you among them: choose one other.',
                default => "Your plan includes {$users['limit']} users, you among them: choose no more than {$room} others.",
            }]);
        }

        return $others->whereNotIn('id', $chosen)->values();
    }

    /** The locations not kept: every active one but the Primary and those chosen - no more than the limit stay. */
    private function closing(array $locations, array $chosen): Collection
    {
        $active  = $locations['locations'];
        $unknown = array_diff($chosen, $active->pluck('id')->all());

        if ($unknown) {
            throw ValidationException::withMessages(['location_ids' => 'Choose from the locations listed.']);
        }

        $kept    = $active->filter(fn ($location) => $location->is_primary || in_array($location->id, $chosen, true));

        if ($kept->count() > $locations['limit']) {
            throw ValidationException::withMessages(['location_ids' => match ($locations['limit']) {
                1       => 'Your plan includes one location: only the Primary Location stays active.',
                2       => 'Your plan includes 2 locations, the Primary among them: choose one other.',
                default => "Your plan includes {$locations['limit']} locations, the Primary among them: choose no more than ".($locations['limit'] - 1).' others.',
            }]);
        }

        return $active->reject(fn ($location) => $kept->contains('id', $location->id))->values();
    }
}
