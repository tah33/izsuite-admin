<?php

namespace App\Services\Shared;

use App\Models\User\Workspace;
use App\Repositories\Shared\IdempotencyKeyRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A create sent more than once makes one record (Doc 14 section 22 - Phase
 * A). The frontend sends an Idempotency-Key with each create, the same one
 * when it sends a request again whose answer never arrived. The first
 * request with a key runs, and its answer is kept for a day; a repeat gets
 * that answer, marked Idempotent-Replayed, instead of running again. A
 * repeat while the first still runs is refused (409); the same key with
 * different data too (422). A request that fails gives its key back, so the
 * corrected form can be sent with it again. Without a key, nothing changes.
 */
class IdempotentRequests
{
    public function __construct(
        private IdempotencyKeyRepository $keys,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key  = $request->header('Idempotency-Key');
        $user = $request->user();

        if ($key === null || $user === null) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $key)) {
            return response()->json(['message' => 'The Idempotency-Key header should be 8 to 100 letters, digits, dashes or underscores.'], 400);
        }

        $route       = (string) ($request->route()?->getName() ?? $request->path());
        $fingerprint = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());
        $workspace   = $request->attributes->get('workspace');

        $record      = $this->keys->claim([
            'user_id'      => $user->id,
            'workspace_id' => $workspace instanceof Workspace ? $workspace->id : null,
            'key'          => $key,
            'route'        => $route,
            'fingerprint'  => $fingerprint,
        ]);

        if (! $record) {
            return $this->repeat($user->id, $key, $fingerprint);
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->keys->release($record);

            throw $e;
        }

        $response->isSuccessful()
            ? $this->keys->answer($record, $response->getStatusCode(), (string) $response->getContent())
            : $this->keys->release($record);

        return $response;
    }

    /** The answer for a key used already: the first request's, once it has one. */
    private function repeat(int $userId, string $key, string $fingerprint): Response
    {
        $first = $this->keys->find($userId, $key);

        if ($first && $first->fingerprint !== $fingerprint) {
            return response()->json(['message' => 'This key was used for a different request. Reload the page and try again.'], 422);
        }

        if (! $first?->answered()) {
            return response()->json(['message' => 'This is being saved already. Please wait a moment.'], 409);
        }

        return response($first->response, $first->status_code, ['Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true']);
    }
}
