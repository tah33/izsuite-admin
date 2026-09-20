<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Users\UserResource;
use App\Services\Api\Auth\AuthService;
use App\Services\Api\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * Create a frontend account and email it an activation code.
     *
     * No token here, unlike login(): the account is not usable until the code
     * comes back, so the frontend's next screen is the OTP box, not the app.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());

            return response()->json([
                'message' => sprintf(
                    'Your account is ready. Enter the %d-digit code we sent to %s to activate it - the code expires in %d minutes.',
                    EmailVerificationService::OTP_LENGTH,
                    $user->email,
                    EmailVerificationService::OTP_TTL_MINUTES,
                ),
                'action'             => 'verification_required',
                'sent_to'            => $user->email,
                'expires_in_minutes' => EmailVerificationService::OTP_TTL_MINUTES,
                'user'               => new UserResource($user->loadMissing('role')),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
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
     * Change the password of the signed-in account.
     *
     * Validation is deliberately identical to the admin profile form: the same
     * `current_password` check and the same Password::defaults() policy, so
     * there is no weaker way in through the API.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $revoked = $this->authService->changePassword($request->user(), $request->validated()['password']);

            return response()->json([
                'message'        => $revoked > 0
                    ? "Your password has been changed. $revoked other session".($revoked === 1 ? ' was' : 's were').' signed out.'
                    : 'Your password has been changed.',
                'revoked_tokens' => $revoked,
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
