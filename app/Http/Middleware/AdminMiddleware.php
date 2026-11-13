<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel's door (Doc 15 section 15.2): only an admin, whose account
 * is switched on and whose session has not ended - by 30 idle minutes, or by
 * their sessions being ended. An admin who signed in without two-factor sees
 * only its set-up until it is done.
 */
class AdminMiddleware
{
    public function __construct(
        protected AdminSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        if ($reason = $this->sessions->endedBecause($request->session(), $user)) {
            $this->sessions->end($request->session());

            return $request->expectsJson()
                ? response()->json(['message' => $reason], 401)
                : redirect()->route('login')->withErrors(['email' => $reason]);
        }

        if ($this->sessions->mustSetUpTwoFactor($request->session(), $user) && ! $request->routeIs('admin.two-factor.*')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Set up two-factor sign-in first.'], 403)
                : redirect()->route('admin.two-factor.setup');
        }

        $this->sessions->touch($request->session());

        return $next($request);
    }
}
