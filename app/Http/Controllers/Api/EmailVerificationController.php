<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ResendVerificationRequest;
use App\Http\Requests\Api\Auth\VerifyEmailRequest;
use App\Services\Api\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;

/**
 * The activation step between registering and signing in.
 *
 * Verification does not hand out a token: the frontend sends the user to the
 * sign-in screen afterwards, so the password is what turns a verified account
 * into a session, exactly as it would on any later visit.
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        protected EmailVerificationService $verificationService,
    ) {}

    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $this->verificationService->verify($data['email'], $data['otp']);

            return response()->json([
                'message' => 'Your email address has been verified. You can sign in now.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * 422 with `account_not_found` if the address has no account, 429 with
     * `cooldown_active` if a code went out moments ago, and a plain 200 that
     * says so if the account is already active.
     */
    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        try {
            $email = $request->validated()['email'];

            $outcome = $this->verificationService->resend($email);

            if ($outcome === 'already_verified') {
                return response()->json([
                    'message' => sprintf('%s is already verified - you can sign in.', $email),
                    'action'  => 'already_verified',
                ]);
            }

            return response()->json([
                'message' => sprintf(
                    "We sent a new %d-digit code to %s. It expires in %d minutes - check your spam folder if it doesn't arrive.",
                    EmailVerificationService::OTP_LENGTH,
                    $email,
                    EmailVerificationService::OTP_TTL_MINUTES,
                ),
                'sent_to'             => $email,
                'expires_in_minutes'  => EmailVerificationService::OTP_TTL_MINUTES,
                'retry_after_seconds' => EmailVerificationService::RESEND_COOLDOWN_SECONDS,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
