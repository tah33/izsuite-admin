<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\StoreAccountEmailRequest;
use App\Http\Requests\Api\Ecommerce\StoreResetPasswordRequest;
use App\Http\Requests\Api\Ecommerce\StoreSignInRequest;
use App\Http\Requests\Api\Ecommerce\StoreSignUpRequest;
use App\Http\Requests\Api\Ecommerce\StoreVerifyRequest;
use App\Http\Resources\Ecommerce\StoreSignInResource;
use App\Services\Api\Ecommerce\StorefrontCustomerAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A storefront customer's account, before they are signed in (Doc 19 Phase
 * E1): signing up, verifying their e-mail, a new code, signing in and out,
 * and a forgotten password - on the store at its code, without a business
 * user's sign-in.
 */
class StoreAccountAuthController extends Controller
{
    public function __construct(
        protected StorefrontCustomerAuth $auth,
    ) {}

    public function signUp(StoreSignUpRequest $request, string $code): JsonResponse
    {
        try {
            $account = $this->auth->signUp($code, $request->validated());

            return response()->json([
                'message' => $account['token'] ? 'Your account is ready.' : 'Your account is made: verify your e-mail with the code we sent.',
                'data'    => new StoreSignInResource($account),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function verify(StoreVerifyRequest $request, string $code): JsonResponse
    {
        try {
            $account = $this->auth->verify($code, $request->validated('email'), $request->validated('code'));

            return response()->json(['message' => 'Your e-mail is verified.', 'data' => new StoreSignInResource($account)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resend(StoreAccountEmailRequest $request, string $code): JsonResponse
    {
        try {
            $this->auth->resend($code, $request->validated('email'));

            return response()->json(['message' => 'If an account waits for this e-mail to be verified, a new code is on its way.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function signIn(StoreSignInRequest $request, string $code): JsonResponse
    {
        try {
            $account = $this->auth->signInWith($code, $request->validated('email'), $request->validated('password'), (string) $request->ip());

            return response()->json(['message' => 'You are signed in.', 'data' => new StoreSignInResource($account)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function signOut(Request $request): JsonResponse
    {
        try {
            $this->auth->signOut($request->attributes->get('storeCustomerToken'));

            return response()->json(['message' => 'You are signed out.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function forgot(StoreAccountEmailRequest $request, string $code): JsonResponse
    {
        try {
            $this->auth->forgot($code, $request->validated('email'));

            return response()->json(['message' => 'If an account uses this e-mail, a code to choose a new password is on its way.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reset(StoreResetPasswordRequest $request, string $code): JsonResponse
    {
        try {
            $data    = $request->validated();
            $account = $this->auth->reset($code, $data['email'], $data['code'], $data['password']);

            return response()->json(['message' => 'Your new password is saved, and you are signed in.', 'data' => new StoreSignInResource($account)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
