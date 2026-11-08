<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Profile\SessionResource;
use App\Services\Api\Profile\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** My Profile > Active Sessions (Doc 12 sections 22 and 23): the user's own signed-in devices. */
class ProfileSessionController extends Controller
{
    public function __construct(
        protected SessionService $sessionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => SessionResource::collection($this->sessionService->list($request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function signOut(Request $request, int $id): JsonResponse
    {
        try {
            $this->sessionService->signOut($request->user(), $id);

            return response()->json(['message' => 'That device is signed out.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function signOutOthers(Request $request): JsonResponse
    {
        try {
            $this->sessionService->signOutOthers($request->user());

            return response()->json(['message' => 'Every other device is signed out.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
