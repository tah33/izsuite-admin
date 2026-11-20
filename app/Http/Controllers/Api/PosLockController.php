<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SavePosPinRequest;
use App\Http\Requests\Api\Pos\UnlockPosRequest;
use App\Http\Resources\Pos\PosLockResource;
use App\Services\Api\Pos\PosLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A cashier's PIN, and locking and unlocking their register (Doc 18 Phase I1). */
class PosLockController extends Controller
{
    public function __construct(
        protected PosLockService $locks,
    ) {}

    public function pin(SavePosPinRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $this->locks->pin($workspace, $request->validated('pin'), $request->validated('password'));

            return response()->json(['message' => 'Your PIN is saved.', 'data' => new PosLockResource(['locked' => false, 'has_pin' => true])]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function lock(Request $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $session   = $this->locks->lock($workspace);

            return response()->json([
                'message' => "{$session->register->name} ({$session->register->code}) is locked.",
                'data'    => new PosLockResource(['locked' => true, 'has_pin' => $this->locks->hasPin()]),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function unlock(UnlockPosRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $session   = $this->locks->unlock($workspace, $request->validated('pin'), $request->validated('password'));

            return response()->json([
                'message' => "{$session->register->name} ({$session->register->code}) is open again.",
                'data'    => new PosLockResource(['locked' => false, 'has_pin' => $this->locks->hasPin()]),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
