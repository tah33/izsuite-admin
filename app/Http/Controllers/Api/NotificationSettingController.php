<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notifications\SaveNotificationPreferencesRequest;
use App\Http\Resources\Notifications\NotificationPreferencesResource;
use App\Services\Shared\Notifications\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Notifications & Email (Doc 11 section 22 - Doc 14 Phase B2): which notifications the business sends, in the app and by e-mail. */
class NotificationSettingController extends Controller
{
    public function __construct(
        protected NotificationPreferences $preferences,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new NotificationPreferencesResource($this->preferences->business($this->currentWorkspace($request))),
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
                'message' => 'Notification settings saved.',
                'data'    => new NotificationPreferencesResource($this->preferences->saveBusiness($this->currentWorkspace($request), $request->validated('preferences'))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
