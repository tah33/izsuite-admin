<?php

namespace App\Http\Middleware;

use App\Services\Shared\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiWriteActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldAutoLog($request, $response)) {
            return $response;
        }

        $route    = $request->route();
        $method   = strtoupper($request->method());
        $action   = $this->resolveActionFromMethod($method);

        ActivityLogService::record(
            $action,
            "{$method} {$request->path()} completed with status {$response->getStatusCode()}",
            properties: [
                'source'      => 'api_auto',
                'http_method' => $method,
                'path'        => '/'.$request->path(),
                'route_name'  => $route?->getName(),
                'route_uri'   => $route?->uri(),
                'status_code' => $response->getStatusCode(),
            ]
        );

        return $response;
    }

    private function shouldAutoLog(Request $request, Response $response): bool
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return false;
        }

        if ($request->attributes->get('activity_log_written')) {
            return false;
        }

        if (! in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        return $response->getStatusCode() < 400;
    }

    private function resolveActionFromMethod(string $method): string
    {
        return match ($method) {
            'POST'   => 'created',
            'DELETE' => 'deleted',
            default  => 'updated',
        };
    }
}
