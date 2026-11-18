<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Events\AppDeactivated;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceAppLog;
use App\Repositories\Admin\MenuItemRepository;
use App\Repositories\User\AppOpenItemsRepository;
use App\Repositories\User\WorkspaceAppRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Reports\ReportCatalog;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an app off for a business (Doc 01 sections 8 and 9, Doc 13 sections
 * 8 and 10) - by its owner, or a role with Manage Apps.
 *
 * A Full app falls back to its Lite edition where the plan allows that;
 * otherwise the family's row becomes inactive and its records stay readable.
 * While other active apps need it, it is refused - unless they are to be
 * turned off with it, each altogether, the furthest first.
 *
 * impact() says beforehand what goes: the pages, the reports, the apps that
 * need it, and what is still open in its records.
 */
class DeactivateApp
{
    public function __construct(
        private AppDependencies $dependencies,
        private WorkspaceAppRepository $workspaceApps,
        private WorkspaceRepository $workspaces,
        private WorkspaceAccess $access,
        private MenuItemRepository $menu,
        private AppOpenItemsRepository $openItems,
    ) {}

    /**
     * @return Application|null the Lite edition it fell back to, if any
     */
    public function handle(Workspace $workspace, Application $app, User $by, bool $withDependents = false): ?Application
    {
        abort_unless($this->access->allows($by, $workspace, 'settings.apps'), 403, 'Your role does not allow turning apps on or off.');

        $apps = WorkspaceApps::for($workspace);
        $row  = $apps->row($app->family_key);

        if (! $row?->isActive() || $row->app_id !== $app->id) {
            $this->refuse("{$app->name} is not turned on.");
        }

        $fallback   = $this->fallbackFor($workspace, $app);
        $dependents = $this->dependents($apps, $app->family_key, $fallback?->edition);

        if ($dependents->isNotEmpty() && ! $withDependents) {
            $this->refuse('Turn these off first: '.$this->dependencies->brokenBy($apps, $app->family_key, $fallback?->edition)->implode(', ').'.');
        }

        DB::transaction(function () use ($workspace, $app, $fallback, $dependents, $by) {
            foreach ($dependents as $dependent) {
                $this->switchOff($workspace, $dependent->app, null, $by);
            }

            $this->switchOff($workspace, $app, $fallback, $by);
            $this->workspaces->bumpNavVersion($workspace);
        });

        foreach ([...$dependents->pluck('app')->all(), $app] as $off) {
            ActivityLogService::record(
                'updated',
                $off === $app && $fallback ? "Moved {$app->name} back to {$fallback->name}" : "Turned off {$off->name}",
                $workspace,
                ['app' => $off->app_key],
                $by,
            );

            AppDeactivated::dispatch($workspace, $off);
        }

        return $fallback;
    }

    /**
     * What turning $app off does (Doc 13 section 8), said before it is done:
     * the Lite edition that comes back, the apps that need it - turned off
     * with it, if asked - the pages and reports that go, and what is still
     * open in its records, which only it can finish.
     *
     * @return array{falls_back_to: ?string, dependents: list<string>, pages: list<string>, reports: list<string>, open_items: list<array{label: string, count: int}>}
     */
    public function impact(Workspace $workspace, Application $app): array
    {
        $apps     = WorkspaceApps::for($workspace);
        $fallback = $this->fallbackFor($workspace, $app);

        return [
            'falls_back_to' => $fallback?->name,
            'dependents'    => $this->dependents($apps, $app->family_key, $fallback?->edition)->map(fn (WorkspaceApp $row) => $row->app->name)->values()->all(),
            // The pages it shows now: back to Lite, only those Lite lacks go; turned off, all of them.
            'pages'         => $this->menu->pagesOf($app->family_key)
                ->filter(fn ($item) => $app->edition->covers($item->min_edition ?? Edition::Lite)
                    && ($fallback === null || ! $fallback->edition->covers($item->min_edition ?? Edition::Lite)))
                ->pluck('label')->values()->all(),
            'reports'       => $fallback ? [] : array_values(array_column(array_filter(ReportCatalog::namesFor($app->family_key), fn (array $report) => ! $report['shared']), 'name')),
            'open_items'    => $fallback ? [] : $this->openItems->for($workspace, $app->family_key),
        ];
    }

    /** Where turning $app off lands: its Lite edition, if it is Full and the plan still has Lite. */
    public function fallbackFor(Workspace $workspace, Application $app): ?Application
    {
        if ($app->edition !== Edition::Full) {
            return null;
        }

        $lite = $app->liteEdition();

        return $lite && $lite->isAvailable() && $workspace->package?->allows($lite) ? $lite : null;
    }

    /**
     * The active apps that need $family above $remaining - and, in turn, the
     * apps that need those - in the order they can be turned off: furthest first.
     *
     * @return Collection<int, WorkspaceApp>
     */
    private function dependents(WorkspaceApps $apps, string $family, ?Edition $remaining): Collection
    {
        $ordered = collect();
        $seen    = [$family => true];

        $visit = function (string $family, ?Edition $remaining) use (&$visit, $apps, $ordered, &$seen) {
            $direct = $apps->active()->filter(fn (WorkspaceApp $row) => ! isset($seen[$row->family_key]) && $row->app->dependencies->contains(
                fn ($need) => $need->requires_family === $family && ($remaining === null || ! $remaining->covers($need->min_edition))
            ));

            foreach ($direct as $row) {
                $seen[$row->family_key] = true;
                $visit($row->family_key, null);
                $ordered->push($row);
            }
        };

        $visit($family, $remaining);

        return $ordered->values();
    }

    /** Off: back to $fallback, or inactive with its records kept - and logged in the business's app history. */
    private function switchOff(Workspace $workspace, Application $app, ?Application $fallback, User $by): void
    {
        $row = $this->workspaceApps->lockRowOrFail($workspace, $app->family_key);

        $fallback
            ? $this->workspaceApps->update($row, ['app_id' => $fallback->id])                                                    // Sales → Sales Lite
            : $this->workspaceApps->update($row, ['status' => WorkspaceApp::STATUS_INACTIVE, 'deactivated_at' => now()]);      // records kept

        $this->workspaceApps->log($workspace, [
            'app_id'      => $fallback?->id ?? $app->id,
            'from_app_id' => $app->id,
            'action'      => $fallback ? WorkspaceAppLog::FALLBACK : WorkspaceAppLog::DEACTIVATED,
            'user_id'     => $by->id,
        ]);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['app' => $message]);
    }
}
