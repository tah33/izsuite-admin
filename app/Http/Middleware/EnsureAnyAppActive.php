<?php

namespace App\Http\Middleware;

use App\Enums\Edition;
use App\Services\User\Apps\WorkspaceApps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards routes more than one app brings (Doc 17 Phase A2), as
 * EnsureAppActive does one app's. Each parameter is a family with the
 * edition it needs:
 *
 *   Route::middleware('app.any:sales:full,crm')   the full Sales app, or CRM
 *
 * Any one on at its edition passes. One turned off since still lets the
 * routes be read - GET and HEAD only - and anything else is a 403.
 *
 * Runs after `workspace`, which picks the business.
 */
class EnsureAnyAppActive
{
    public function handle(Request $request, Closure $next, string ...$apps): Response
    {
        $workspace = $request->attributes->get('workspace');

        abort_if($workspace === null, 403, 'This app is not turned on for your business.');

        $active    = WorkspaceApps::for($workspace);
        $turnedOff = false;

        foreach ($apps as $app) {
            [$family, $min] = array_pad(explode(':', $app, 2), 2, 'lite');

            if ($active->has($family, Edition::from($min))) {
                return $next($request);
            }

            $turnedOff = $turnedOff || ($active->hasHistory($family) && $active->activeEdition($family) === null);
        }

        if ($turnedOff && $request->isMethodSafe()) {
            return $next($request);
        }

        abort(403, 'This app is not turned on for your business.');
    }
}
