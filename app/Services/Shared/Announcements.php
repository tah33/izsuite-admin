<?php

namespace App\Services\Shared;

use App\Models\Admin\Announcement;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\AnnouncementRepository;
use App\Repositories\User\WorkspaceAppRepository;
use App\Repositories\User\WorkspaceRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

/**
 * The platform announcements people see (Doc 15 section 15.21 - Phase F1):
 * in the workspace, those live now whose audience reaches the business -
 * all businesses, those on the plans chosen, in the countries chosen (from
 * its profile), with one of the apps chosen on, or named; in the admin
 * panel, those for izSuite's admins only. The most urgent first. Each
 * person may dismiss one for themselves, unless it is not dismissible.
 */
class Announcements
{
    public function __construct(
        private AnnouncementRepository $announcements,
        private WorkspaceRepository $workspaces,
        private WorkspaceAppRepository $apps,
    ) {}

    /** What $user sees in $workspace now. */
    public function for(Workspace $workspace, User $user): Collection
    {
        $live = $this->announcements->liveAt(now(), forAdmins: false);

        if ($live->isEmpty()) {
            return collect();
        }

        $facts = $this->factsOf($workspace);

        return $this->shown($live->filter(fn (Announcement $announcement) => $this->reaches($announcement, $facts)), $user);
    }

    /** What $admin sees in the admin panel now. */
    public function forAdmins(User $admin): Collection
    {
        return $this->shown($this->announcements->liveAt(now(), forAdmins: true), $admin);
    }

    /** $user puts announcement $id away - one they see now, and may dismiss - in $workspace, or in the admin panel without one. */
    public function dismiss(int $id, User $user, ?Workspace $workspace = null): Announcement
    {
        $announcement = ($workspace ? $this->for($workspace, $user) : $this->forAdmins($user))->firstWhere('id', $id) ?? abort(404, 'Announcement not found.');

        if (! $announcement->dismissible) {
            throw new HttpResponseException(response()->json(['message' => 'This announcement stays until it ends.'], 409));
        }

        // A person's own choice, not the business's activity: no entry in its log.
        ActivityLogService::skipForThisRequest();

        $this->announcements->dismiss($announcement, $user);

        return $announcement;
    }

    /** $live less those $user dismissed (when they may), the most urgent first, then the newest. */
    private function shown(Collection $live, User $user): Collection
    {
        $dismissed = $live->isEmpty() ? [] : $this->announcements->dismissedBy($user, $live->pluck('id')->all());

        return $live
            ->reject(fn (Announcement $announcement) => $announcement->dismissible && in_array($announcement->id, $dismissed, true))
            ->sortBy([fn (Announcement $a, Announcement $b) => $b->urgency() <=> $a->urgency(), fn (Announcement $a, Announcement $b) => $b->id <=> $a->id])
            ->values()
            ->toBase();
    }

    /** @param array{package: ?string, country: ?string, apps: list<string>, id: int} $facts */
    private function reaches(Announcement $announcement, array $facts): bool
    {
        $targets = $announcement->targets ?? [];

        return match ($announcement->audience) {
            Announcement::ALL        => true,
            Announcement::PACKAGES   => in_array($facts['package'], $targets, true),
            Announcement::COUNTRIES  => in_array($facts['country'], $targets, true),
            Announcement::APPS       => array_intersect($facts['apps'], $targets) !== [],
            Announcement::BUSINESSES => in_array($facts['id'], array_map('intval', $targets), true),
            default                  => false,
        };
    }

    /** @return array{package: ?string, country: ?string, apps: list<string>, id: int} what an audience asks of a business */
    private function factsOf(Workspace $workspace): array
    {
        return [
            'package' => $this->workspaces->packageOf($workspace)?->key,
            'country' => $this->workspaces->countryOf($workspace),
            'apps'    => $this->apps->activeFamilies($workspace),
            'id'      => $workspace->id,
        ];
    }
}
