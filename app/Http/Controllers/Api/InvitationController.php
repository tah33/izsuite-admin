<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Members\RegisterFromInvitationRequest;
use App\Http\Resources\Members\InvitationResource;
use App\Http\Resources\Members\JoinedBusinessResource;
use App\Http\Resources\Users\UserResource;
use App\Services\Api\Members\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An invitation's link (Doc 12 section 6): what it is, accepting it with the
 * account signed in, or making the account that accepts it - signed in, as
 * a login would answer.
 */
class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService,
    ) {}

    public function show(string $token): JsonResponse
    {
        try {
            return response()->json([
                'data' => new InvitationResource($this->invitationService->show($token)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        try {
            $member = $this->invitationService->accept($token, $request->user());

            return response()->json([
                'message' => "Welcome to {$member->workspace->name}.",
                'data'    => new JoinedBusinessResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function register(RegisterFromInvitationRequest $request, string $token): JsonResponse
    {
        try {
            ['user' => $user, 'token' => $session, 'member' => $member] = $this->invitationService->register($token, $request->validated());

            return response()->json([
                'message'    => "Welcome to {$member->workspace->name}.",
                'token'      => $session->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => to_display_timezone_iso($session->accessToken->expires_at),
                'user'       => new UserResource($user),
                'data'       => new JoinedBusinessResource($member),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
