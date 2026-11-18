<?php

namespace App\Services\User\Workspaces;

use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\WorkspaceMemberRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Files\StorageUsage;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A package's count limits (Doc 01 section 4, Doc 13), as the admin sets them:
 * how many businesses on it one account may own, and how many users and
 * locations each business has. Empty is no limit.
 *
 * Adding past a limit is refused with a 403 that says so. A business already
 * over one - after a downgrade, or a limit lowered - keeps everything; it only
 * cannot add more, until its owner chooses what stays active (OverLimitService).
 * A business's users are its members who are active or invited (Doc 12
 * section 3) - its owner among them; its locations, the active ones. Its
 * limits are those of the plan's version it is held to, if any (Doc 15
 * Phase E3b: Workspace::terms()).
 */
class PlanLimits
{
    /** The share of a limit at which it warns that it is nearly reached (Doc 13 section 14). */
    public const WARNING_PERCENT = 80;

    public function __construct(
        protected PackageRepository $packageRepository,
        protected WorkspaceRepository $workspaceRepository,
        protected LocationRepository $locationRepository,
        protected WorkspaceMemberRepository $memberRepository,
        protected StorageUsage $storageUsage,
    ) {}

    /** A new business starts on Billing Starter, so the account may own only so many there. */
    public function ensureCanAddBusiness(User $owner): void
    {
        $starter = $this->packageRepository->starter();

        if ($starter?->business_limit !== null && $this->workspaceRepository->countForUserOnPackage($owner, $starter->id) >= $starter->business_limit) {
            $this->refuse($starter->business_limit === 1
                ? "Your account already has its {$starter->name} business. Another business needs a paid plan."
                : "Your account already has {$starter->business_limit} businesses on {$starter->name}. Another business needs a paid plan.");
        }
    }

    public function ensureCanAddUser(Workspace $workspace): void
    {
        $terms = $this->workspaceRepository->termsOf($workspace);

        if ($terms?->user_limit !== null && $this->users($workspace) >= $terms->user_limit) {
            $this->refuse($this->reached($terms, $terms->user_limit, 'user', 'users').' Upgrade the plan to add users.');
        }
    }

    /**
     * The business moving to $owner (Doc 12 section 9): one on Billing Starter
     * only while they may own another business there.
     */
    public function ensureCanTakeOver(User $owner, Workspace $workspace): void
    {
        $starter = $this->packageRepository->starter();

        if ($starter?->business_limit !== null && $this->workspaceRepository->packageOf($workspace)?->id === $starter->id
            && $this->workspaceRepository->countForUserOnPackage($owner, $starter->id) >= $starter->business_limit) {
            $this->refuse("{$owner->name} already has a {$starter->name} business, so this one cannot move to them on {$starter->name}.");
        }
    }

    /** A location added - or an inactive one turned on again - needs room under the plan. */
    public function ensureCanAddLocation(Workspace $workspace): void
    {
        $terms = $this->workspaceRepository->termsOf($workspace);

        if ($terms?->location_limit !== null && $this->locations($workspace) >= $terms->location_limit) {
            $this->refuse($this->reached($terms, $terms->location_limit, 'location', 'locations').' Upgrade the plan to add another.');
        }
    }

    /**
     * Settings > Subscription's Usage and Limits (Doc 13 sections 13.11 and
     * 13.14): the business's storage as StorageUsage measures it, and how
     * many users and locations it has of how many its plan gives it (null for
     * no limit), each with its state (see count()).
     *
     * @return array{storage: array, users: array{used: int, limit: ?int, state: string}, locations: array{used: int, limit: ?int, state: string}}
     */
    public function usage(Workspace $workspace): array
    {
        $terms = $this->workspaceRepository->termsOf($workspace);

        return [
            'storage'   => $this->storageUsage->summary($workspace),
            'users'     => self::count($this->users($workspace), $terms?->user_limit),
            'locations' => self::count($this->locations($workspace), $terms?->location_limit),
        ];
    }

    /**
     * $used of $limit, with its state: "ok"; "warning" from WARNING_PERCENT of
     * the limit; "full" at it; "over" past it - after a move to a lower plan,
     * until the owner chooses what stays active.
     *
     * @return array{used: int, limit: ?int, state: string}
     */
    public static function count(int $used, ?int $limit): array
    {
        return ['used' => $used, 'limit' => $limit, 'state' => match (true) {
            $limit === null                               => 'ok',
            $used > $limit                                => 'over',
            $used === $limit                              => 'full',
            $used * 100 >= $limit * self::WARNING_PERCENT => 'warning',
            default                                       => 'ok',
        }];
    }

    /** Its active and invited users - the owner counted even where no member row says so yet. */
    public function users(Workspace $workspace): int
    {
        return max(1, $this->memberRepository->countCounted($workspace));
    }

    /** Its active locations: an inactive one takes no new transactions, so it is outside the limit. */
    public function locations(Workspace $workspace): int
    {
        return $this->locationRepository->countActiveFor($workspace);
    }

    /** "Billing Starter includes one user." - by the plan's terms the business is held to (Doc 15 Phase E3b). */
    private function reached(Package|PackageVersion $terms, int $limit, string $one, string $many): string
    {
        $plan = $terms instanceof PackageVersion ? $terms->package->name : $terms->name;

        return "{$plan} includes ".($limit === 1 ? "one {$one}" : "{$limit} {$many}").'.';
    }

    private function refuse(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 403));
    }
}
