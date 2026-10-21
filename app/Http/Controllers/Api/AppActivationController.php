<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Frontend\Application;
use App\Models\User\Workspace;
use App\Services\User\Apps\ActivateApp;
use App\Services\User\Apps\DeactivateApp;
use App\Services\User\Apps\NavigationBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turning apps on and off from the Apps Center. Each answer carries the new
 * navigation, so the sidebar updates without a second request.
 */
class AppActivationController extends Controller
{
    public function __construct(
        protected ActivateApp $activateApp,
        protected DeactivateApp $deactivateApp,
        protected NavigationBuilder $navigation,
    ) {}

    public function activate(Request $request, string $appKey): JsonResponse
    {
        try {
            $workspace = $this->workspace($request);
            $app       = $this->app($appKey);
            $replaced  = $this->activateApp->handle($workspace, $app, $request->user());

            return response()->json([
                'message' => $replaced ? "{$app->name} replaced {$replaced->name}." : "{$app->name} is on.",
                'data'    => $this->navigation->for($workspace, $request->user()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deactivate(Request $request, string $appKey): JsonResponse
    {
        try {
            $workspace = $this->workspace($request);
            $app       = $this->app($appKey);
            $fallback  = $this->deactivateApp->handle($workspace, $app, $request->user());

            return response()->json([
                'message' => $fallback ? "{$app->name} is off. {$fallback->name} is back." : "{$app->name} is off. Its records are kept.",
                'data'    => $this->navigation->for($workspace, $request->user()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('workspace');

        abort_if($workspace === null, 404, 'Create a workspace first.');

        return $workspace;
    }

    /** By its key, listed or not: an app hidden from the catalog can still be turned off. */
    private function app(string $appKey): Application
    {
        $app = Application::where('app_key', $appKey)->first();

        abort_if($app === null, 404, 'App not found.');

        return $app;
    }
}
