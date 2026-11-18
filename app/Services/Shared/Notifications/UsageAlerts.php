<?php

namespace App\Services\Shared\Notifications;

use App\Models\User\Workspace;
use App\Repositories\User\WorkspaceRepository;
use App\Services\User\Workspaces\PlanLimits;
use App\Support\Bytes;

/**
 * The plan's limits nearly reached, reached or passed (Doc 13 section 19 -
 * Doc 14 Phase B2), as the Dashboard warns of them: storage nearly full or
 * full; users or active locations near their limit, at it - unless the limit
 * is one, as every Billing Starter business is from its first day - or past
 * it after a move to a lower plan. Checked hourly, for those who manage the
 * subscription, each limit's state once for as long as the limit stays what
 * it is.
 */
class UsageAlerts
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private PlanLimits $limits,
        private NotificationAudience $audience,
        private Notifier $notifier,
    ) {}

    /** Checks every business; answers with how many notifications went out. */
    public function run(): int
    {
        $reached = 0;

        foreach ($this->workspaces->lazyWithPackage() as $workspace) {
            $reached += $this->check($workspace);
        }

        return $reached;
    }

    public function check(Workspace $workspace): int
    {
        $usage   = $this->limits->usage($workspace);
        $plan    = $workspace->package?->name ?? 'Your plan';
        $notices = array_filter([
            $this->storage($workspace, $usage['storage']),
            $this->count($workspace, 'users', 'users', $usage['users'], $plan),
            $this->count($workspace, 'locations', 'active locations', $usage['locations'], $plan),
        ]);

        if (! $notices) {
            return 0;
        }

        $reached = 0;
        $users   = $this->audience->withPermission($workspace, 'settings.subscription');

        foreach ($notices as $notice) {
            $reached += $this->notifier->send($workspace, $users, $notice);
        }

        return $reached;
    }

    /** @param  array{used_bytes: int, limit_bytes: ?int, state: string}  $storage */
    private function storage(Workspace $workspace, array $storage): ?Notice
    {
        [$title, $body] = match ($storage['state']) {
            'warning' => ['File storage is nearly full', Bytes::format($storage['used_bytes']).' of '.Bytes::format($storage['limit_bytes']).' used. Delete files you no longer need, or move to a plan with more room.'],
            'full'    => ['File storage is full', 'Files stay available, but uploads stop until files are deleted or the plan has more room.'],
            default   => [null, null],
        };

        return $title ? $this->notice($workspace, 'storage', $storage['state'], $storage['limit_bytes'], $title, $body, '/settings?tab=storage') : null;
    }

    /** @param  array{used: int, limit: ?int, state: string}  $count */
    private function count(Workspace $workspace, string $limit, string $things, array $count, string $plan): ?Notice
    {
        [$title, $body] = match (true) {
            $count['state'] === 'over'                         => ["More {$things} than the plan includes", "{$count['used']} {$things} where {$plan} includes {$count['limit']}: the Business Owner chooses which stay active."],
            $count['state'] === 'warning'                      => [ucfirst($things)." nearly at the plan's limit", "{$count['used']} of the {$count['limit']} {$things} {$plan} includes are in use. Upgrade the plan to add more."],
            $count['state'] === 'full' && $count['limit'] > 1 => ["All the {$things} the plan includes are in use", "All {$count['limit']} {$things} {$plan} includes are in use. Upgrade the plan to add another."],
            default                                            => [null, null],
        };

        return $title ? $this->notice($workspace, $limit, $count['state'], $count['limit'], $title, $body, '/settings?tab=subscription') : null;
    }

    /** Once per business, limit and state while the limit stays the same: a new plan's limits are new. */
    private function notice(Workspace $workspace, string $limit, string $state, int $value, string $title, string $body, string $link): Notice
    {
        return new Notice(
            trigger: "usage.{$limit}.{$state}",
            key: "usage.{$limit}.{$state}:{$workspace->id}:{$value}",
            title: $title,
            body: $body,
            link: $link,
            subject: $workspace,
            email: true,
        );
    }
}
