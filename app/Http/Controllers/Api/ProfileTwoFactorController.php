<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\Profile\TwoFactorPasswordRequest;
use App\Http\Resources\Profile\RecoveryCodesResource;
use App\Http\Resources\Profile\TwoFactorSetupResource;
use App\Services\Api\Profile\TwoFactorService;
use Illuminate\Http\JsonResponse;

/** My Profile > Two-Factor Authentication (Doc 12 section 22): the user's own, with an authenticator app. */
class ProfileTwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService,
    ) {}

    public function setup(TwoFactorPasswordRequest $request): JsonResponse
    {
        try {
            $setup = $this->twoFactorService->start($request->user(), $request->validated('password'));

            return response()->json([
                'message' => 'Scan the QR code with your authenticator app, or type the key in, then enter the code it shows.',
                'data'    => new TwoFactorSetupResource($setup),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        try {
            $codes = $this->twoFactorService->confirm($request->user(), $request->validated('code'));

            return response()->json([
                'message' => 'Two-factor authentication is on. Keep these recovery codes somewhere safe: each signs in once without your app.',
                'data'    => new RecoveryCodesResource($codes),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function recoveryCodes(TwoFactorPasswordRequest $request): JsonResponse
    {
        try {
            $codes = $this->twoFactorService->newRecoveryCodes($request->user(), $request->validated('password'));

            return response()->json([
                'message' => 'New recovery codes are ready. The ones before no longer work.',
                'data'    => new RecoveryCodesResource($codes),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function disable(TwoFactorPasswordRequest $request): JsonResponse
    {
        try {
            $this->twoFactorService->disable($request->user(), $request->validated('password'));

            return response()->json(['message' => 'Two-factor authentication is off.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
