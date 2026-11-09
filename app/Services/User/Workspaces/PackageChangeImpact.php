<?php

namespace App\Services\User\Workspaces;

use App\Models\Billing\Package;
use App\Models\User\Workspace;
use App\Services\Files\StorageUsage;
use App\Services\User\Apps\SyncAppsWithPackage;

/**
 * What moving a business to another plan would do (Doc 13 sections 13 and
 * 14), shown before the move: the apps the new plan turns off - or back to
 * their Lite edition - and the business's users, locations and storage
 * against the new plan's limits.
 *
 * Over a limit the move itself takes nothing away: the owner then chooses the
 * users and locations that stay active (OverLimitService), and over storage
 * the files stay while uploads stop.
 */
class PackageChangeImpact
{
    public function __construct(
        private SyncAppsWithPackage $sync,
        private PlanLimits $planLimits,
        private StorageUsage $storageUsage,
    ) {}

    /**
     * @return array{direction: string, apps: list<array{name: string, lite: ?string}>, users: array, locations: array, storage: array}
     */
    public function of(Workspace $workspace, Package $to): array
    {
        $from = $workspace->package;

        return [
            'direction' => match (true) {
                $from === null || $to->rank > $from->rank => 'up',
                $to->rank < $from->rank                   => 'down',
                default                                   => 'same',
            },
            'apps'      => array_map(fn (array $change) => ['name' => $change['from']->name, 'lite' => $change['to']?->name], $this->sync->changes($workspace, $to)),
            'users'     => PlanLimits::count($this->planLimits->users($workspace), $to->user_limit),
            'locations' => PlanLimits::count($this->planLimits->locations($workspace), $to->location_limit),
            'storage'   => PlanLimits::count($this->storageUsage->usedBytes($workspace), $to->storage_limit_mb === null ? null : $to->storage_limit_mb * 1024 * 1024),
        ];
    }
}
