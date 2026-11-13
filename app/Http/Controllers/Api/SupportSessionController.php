<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RedeemSupportSessionRequest;
use App\Http\Resources\Users\SupportSessionResource;
use App\Http\Resources\Users\UserResource;
use App\Services\Shared\SupportSessionService;
use Illuminate\Http\JsonResponse;

/**
 * The app's half of a support session (Doc 15 section 15.22): the one-time
 * code from the admin panel's link, traded for an API session in the
 * business - answered just like a sign-in, with the session's details.
 */
class SupportSessionController extends Controller
{
    public function __construct(
        protected SupportSessionService $sessions,
    ) {}

    public function redeem(RedeemSupportSessionRequest $request): JsonResponse
    {
        try {
            ['user' => $user, 'token' => $token, 'session' => $session, 'back_url' => $backUrl] = $this->sessions->redeem($request->validated('code'));

            return response()->json([
                'message'         => "izSuite support session in {$session->workspace?->name}.",
                'token'           => $token->plainTextToken,
                'token_type'      => 'Bearer',
                'expires_at'      => to_display_timezone_iso($token->accessToken->expires_at),
                'user'            => new UserResource($user),
                'support_session' => new SupportSessionResource(['session' => $session, 'back_url' => $backUrl]),
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
