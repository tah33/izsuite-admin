<?php

namespace App\Services\Api\Apps;

use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\AppRepository;
use App\Services\User\Apps\ActivateApp;
use App\Services\User\Apps\DeactivateApp;
use App\Services\User\Apps\NavigationBuilder;

/**
 * Turning apps on and off from the Apps Center. Each answer says what
 * happened and carries the new navigation, so the sidebar updates without a
 * second request.
 */
class AppActivationService
{
    public function __construct(
        protected AppRepository $appRepository,
        protected ActivateApp $activateApp,
        protected DeactivateApp $deactivateApp,
        protected NavigationBuilder $navigation,
    ) {}

    /** @return array{message: string, navigation: array} */
    public function activate(Workspace $workspace, string $appKey, User $user): array
    {
        $app      = $this->app($appKey);
        $replaced = $this->activateApp->handle($workspace, $app, $user);

        return [
            'message'    => $replaced ? "{$app->name} replaced {$replaced->name}." : "{$app->name} is on.",
            'navigation' => $this->navigation->for($workspace, $user),
        ];
    }

    /**
     * Off - with the apps that need it, when $withDependents (Doc 13 section 10).
     *
     * @return array{message: string, navigation: array}
     */
    public function deactivate(Workspace $workspace, string $appKey, User $user, bool $withDependents = false): array
    {
        $app      = $this->app($appKey);
        $others   = $withDependents ? $this->deactivateApp->impact($workspace, $app)['dependents'] : [];
        $fallback = $this->deactivateApp->handle($workspace, $app, $user, $withDependents);

        $message = $fallback ? "{$app->name} is off. {$fallback->name} is back." : "{$app->name} is off. Its records are kept.";

        return [
            'message'    => $others ? "{$message} Also off: ".implode(', ', $others).'.' : $message,
            'navigation' => $this->navigation->for($workspace, $user),
        ];
    }

    /** What turning the app off would do, said before it is done (Doc 13 section 8). */
    public function impact(Workspace $workspace, string $appKey): array
    {
        return $this->deactivateApp->impact($workspace, $this->app($appKey));
    }

    /** By its key, listed or not: an app hidden from the catalog can still be turned off. */
    private function app(string $appKey): Application
    {
        $app = $this->appRepository->findByKey($appKey);

        abort_if($app === null, 404, 'App not found.');

        return $app;
    }
}
