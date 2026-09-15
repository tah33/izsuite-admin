<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Users\UserResource;
use App\Services\Api\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * Sign a frontend user in and issue a bearer token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            ['user' => $user, 'token' => $token] = $this->authService->login($request->validated());

            return response()->json([
                'message'    => 'Signed in successfully.',
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => to_display_timezone_iso($token->accessToken->expires_at),
                'user'       => new UserResource($user->loadMissing('role')),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * The user behind the presented token — used by the frontend to restore a
     * session after a reload.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'user' => new UserResource($request->user()->loadMissing('role')),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Revoke the token that made this request.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return response()->json([
                'message' => 'Signed out successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Revoke every token this user holds, including the one making the request.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $revoked = $this->authService->logoutFromAllDevices($request->user());

            return response()->json([
                'message'        => 'Signed out of all devices.',
                'revoked_tokens' => $revoked,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
