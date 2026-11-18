<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notifications\SaveNotificationPreferencesRequest;
use App\Http\Resources\Notifications\NotificationPreferencesResource;
use App\Services\Shared\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** My Profile > Notifications (Doc 12 section 22 - Doc 14 Phase B2): which notifications the user receives, in every business. */
class ProfileNotificationController extends Controller
{
    public function __construct(
        protected NotificationPreferences $preferences,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new NotificationPreferencesResource($this->preferences->user($request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Your notification preferences are saved.',
                'data'    => new NotificationPreferencesResource($this->preferences->saveUser($request->user(), $request->validated('preferences'))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
