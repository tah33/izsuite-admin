<?php

namespace App\Http\Middleware;

use App\Services\Shared\IdempotentRequests;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the routes that create a record (Doc 14 section 22 - Phase A): a
 * repeat with the same Idempotency-Key gets the first answer instead of a
 * second record (IdempotentRequests).
 */
class EnsureIdempotent
{
    public function __construct(
        private IdempotentRequests $requests,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->requests->handle($request, $next);
    }
}
