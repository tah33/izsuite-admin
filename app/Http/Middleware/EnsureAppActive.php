<?php

namespace App\Http\Middleware;

use App\Enums\Edition;
use App\Services\User\Apps\WorkspaceApps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards an app's API routes. Hiding a menu item is not security; this is.
 *
 *   Route::middleware('app:sales')        Sales Lite or Sales
 *   Route::middleware('app:sales,full')   Sales only
 *
 * An app that is on at the edition needed passes. A family that was on before
 * and is now off still lets its records be read - GET and HEAD only - and
 * everything else is a 403. A family on at a lower edition does not open the
 * higher edition's routes even for reading: what only Full shows stays stored,
 * but is not available through Lite (Doc 01 section 9).
 *
 * Runs after `workspace`, which picks the business.
 */
class EnsureAppActive
{
    public function handle(Request $request, Closure $next, string $family, string $min = 'lite'): Response
    {
        $workspace = $request->attributes->get('workspace');

        abort_if($workspace === null, 403, 'This app is not turned on for your business.');

        $apps = WorkspaceApps::for($workspace);

        if ($apps->has($family, Edition::from($min))) {
            return $next($request);
        }

        $turnedOff = $apps->hasHistory($family) && $apps->activeEdition($family) === null;

        if ($turnedOff && $request->isMethodSafe()) {
            return $next($request);
        }

        abort(403, 'This app is not turned on for your business.');
    }
}
