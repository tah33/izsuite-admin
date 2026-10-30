<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginAsRequest;
use App\Http\Resources\Users\LoginAsSessionResource;
use App\Http\Resources\Users\UserResource;
use App\Services\Api\Auth\LoginAsService;
use Illuminate\Http\JsonResponse;

/**
 * The website's half of "Login as": the one-time code from the admin panel's
 * link, traded for a session as the client - answered just like a login.
 */
class LoginAsController extends Controller
{
    public function __construct(
        protected LoginAsService $loginAsService,
    ) {}

    public function redeem(LoginAsRequest $request): JsonResponse
    {
        try {
            ['user' => $user, 'token' => $token, 'back_url' => $backUrl] = $this->loginAsService->redeem($request->validated('code'));

            return response()->json([
                'message'    => "Signed in as {$user->name}.",
                'token'      => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => to_display_timezone_iso($token->accessToken->expires_at),
                'user'       => new UserResource($user),
                'login_as'   => new LoginAsSessionResource($backUrl),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
