<?php

namespace App\Services\Admin;

use App\Models\Admin\Announcement;
use App\Models\User\User;
use App\Repositories\Admin\AnnouncementRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\Countries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Announcements (Doc 15 section 15.21 - Phase F1): announcements
 * made, changed and deleted. Each change counts at once and is in the audit
 * log.
 */
class AnnouncementAdminService
{
    /** The form's list for each audience that names something. */
    private const LISTS = [
        Announcement::PACKAGES   => ['packages', 'Choose a plan at least.'],
        Announcement::COUNTRIES  => ['countries', 'Choose a country at least.'],
        Announcement::APPS       => ['apps', 'Choose an app at least.'],
        Announcement::BUSINESSES => ['businesses', 'Name a business at least.'],
    ];

    public function __construct(
        protected AnnouncementRepository $announcements,
        protected PackageRepository $packages,
        protected AppService $apps,
    ) {}

    public function page(array $filters): LengthAwarePaginator
    {
        return $this->announcements->page($filters, now(), requested_per_page());
    }

    /** What the form offers: the announcement when editing, its businesses written out; the plans, countries and apps. */
    public function form(?int $id = null): array
    {
        $announcement = $id ? $this->find($id) : null;
        $named        = $announcement?->audience === Announcement::BUSINESSES ? ($announcement->targets ?? []) : [];

        return [
            'announcement' => $announcement,
            'businesses'   => implode("\n", $this->announcements->businessLabels($named)),
            'plans'        => $this->packages->allByRank(),
            'countries'    => Countries::all(),
            'apps'         => $this->apps->catalogFamilyNames(),
        ];
    }

    public function create(array $data, User $admin): Announcement
    {
        $announcement = $this->announcements->create([...$this->clean($data), 'created_by' => $admin->id]);

        ActivityLogService::record('created', "Added announcement \"{$announcement->title}\"", $announcement, null, $admin);

        return $announcement;
    }

    public function update(int $id, array $data, User $admin): Announcement
    {
        $announcement = $this->announcements->update($this->find($id), $this->clean($data));

        ActivityLogService::record('updated', "Changed announcement \"{$announcement->title}\"", $announcement, null, $admin);

        return $announcement;
    }

    /** Deleted: gone from everyone's workspace at once. */
    public function delete(int $id, User $admin): void
    {
        $announcement = $this->find($id);

        $this->announcements->delete($announcement);

        ActivityLogService::record('deleted', "Deleted announcement \"{$announcement->title}\"", null, null, $admin);
    }

    private function find(int $id): Announcement
    {
        return $this->announcements->find($id) ?? abort(404);
    }

    /**
     * The audience's list as its targets - the businesses as written, BW- IDs
     * or plain, a name after each allowed, on lines or with commas - and none
     * for all businesses or admins. A link without words reads "Learn more".
     */
    private function clean(array $data): array
    {
        $audience = $data['audience'];
        $list     = self::LISTS[$audience] ?? null;
        $targets  = null;

        if ($list !== null) {
            [$field, $missing] = $list;
            $targets           = $audience === Announcement::BUSINESSES ? $this->businessIds((string) ($data['businesses'] ?? '')) : array_values(array_unique($data[$field] ?? []));

            if ($targets === []) {
                throw ValidationException::withMessages([$field => $missing]);
            }
        }

        $linked   = filled($data['link_url'] ?? null);

        return [
            ...array_diff_key($data, array_flip(['packages', 'countries', 'apps', 'businesses'])),
            'targets'    => $targets,
            'link_url'   => $linked ? $data['link_url'] : null,
            'link_label' => $linked ? (filled($data['link_label'] ?? null) ? $data['link_label'] : 'Learn more') : null,
        ];
    }

    /** @return list<int> the businesses named, each one that exists */
    private function businessIds(string $written): array
    {
        preg_match_all('/^\s*(?:BW-?)?0*(\d+)|,\s*(?:BW-?)?0*(\d+)/im', $written, $found);
        $ids     = array_values(array_unique(array_map('intval', array_filter([...$found[1], ...$found[2]], 'strlen'))));
        $unknown = array_diff($ids, $this->announcements->businessIds($ids));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['businesses' => 'No business has the ID '.sprintf('BW-%06d', reset($unknown)).'.']);
        }

        return $ids;
    }
}
