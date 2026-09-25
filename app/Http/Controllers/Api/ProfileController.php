<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\UpdateProfileRequest;
use App\Http\Resources\Users\UserResource;
use App\Services\Api\Profile\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService,
    ) {}

    /**
     * The signed-in account's profile.
     *
     * Today this is the same body as GET /auth/me. The two answer different
     * questions - "who holds this token" for guards and headers, "what is on my
     * profile" for the profile page - so they are kept apart and can grow in
     * different directions without breaking each other's callers.
     */
    public function show(Request $request): JsonResponse
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

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $this->profileService->update($request->user(), $request->validated());

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user'    => new UserResource($user->loadMissing('role')),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
