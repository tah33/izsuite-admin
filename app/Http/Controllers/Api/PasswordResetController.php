<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\VerifyPasswordOtpRequest;
use App\Services\Api\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordReset,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $email = $request->validated()['email'];

            $this->passwordReset->sendCode($email);

            return response()->json([
                'message'             => sprintf(
                    "We sent a %d-digit code to %s. It expires in %d minutes - check your spam folder if it doesn't arrive.",
                    PasswordResetService::OTP_LENGTH,
                    $email,
                    PasswordResetService::OTP_TTL_MINUTES,
                ),
                'sent_to'             => $email,
                'expires_in_minutes'  => PasswordResetService::OTP_TTL_MINUTES,
                'retry_after_seconds' => PasswordResetService::RESEND_COOLDOWN_SECONDS,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function verifyCode(VerifyPasswordOtpRequest $request): JsonResponse
    {
        try {
            $data       = $request->validated();

            $resetToken = $this->passwordReset->verifyCode($data['email'], $data['otp']);

            return response()->json([
                'message'            => 'Code accepted. Choose a new password.',
                'reset_token'        => $resetToken,
                'expires_in_minutes' => PasswordResetService::RESET_TOKEN_TTL_MINUTES,
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $this->passwordReset->resetPassword($data['email'], $data['reset_token'], $data['password']);

            return response()->json([
                'message' => 'Your password has been reset.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
