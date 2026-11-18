<?php

namespace App\Http\Middleware;

use App\Services\Shared\SupportSessionService;
use App\Support\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a request made on a support session's token (Doc 15 section 15.22)
 * to what the session may do: a session ended or past its end answers 401;
 * one refused by SupportAccess's rules, 403. A request let through carries
 * the session in its attributes (SupportAccess::current()), which keeps it to
 * the session's business (ResolveWorkspace), withholds what a session never
 * sees in full (WorkspaceAccess), and names izSuite support in the Activity
 * Log; the first time it opens a sensitive area, the log says so.
 *
 * In the api group, so it runs before each route's auth: it reads the token
 * through the sanctum guard, which the route's auth then reuses.
 */
class SupportSessionGuard
{
    public function __construct(
        protected SupportSessionService $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ? $request->user('sanctum')?->currentAccessToken() : null;

        if (! $token instanceof PersonalAccessToken || ! str_starts_with((string) $token->name, SupportAccess::TOKEN_PREFIX)) {
            return $next($request);
        }

        $session = $this->sessions->forToken($token);

        if (! $session?->isLive()) {
            return response()->json(['message' => 'This support session has ended.', 'support_session' => true], 401);
        }

        $request->attributes->set(SupportAccess::ATTRIBUTE, $session);

        if ($refusal = $this->sessions->refusal($request, $session)) {
            return $refusal;
        }

        $this->sessions->noteArea($request, $session);

        return $next($request);
    }
}
