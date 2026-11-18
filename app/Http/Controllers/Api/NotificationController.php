<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notifications\ListNotificationsRequest;
use App\Http\Resources\Notifications\NotificationResource;
use App\Http\Resources\Notifications\UnreadNotificationsResource;
use App\Services\Api\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The bell (Doc 14 section 15 - Phase B1): the signed-in user's
 * notifications in the business, how many are unread, and marking them
 * read - one, or all.
 */
class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        try {
            $page = $this->notificationService->page($request->user(), $this->currentWorkspace($request), $request->boolean('unread'), (int) ($request->validated('per_page') ?? 10));

            return response()->json([
                'data'       => NotificationResource::collection($page->getCollection()),
                'pagination' => slim_pagination($page),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function unread(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new UnreadNotificationsResource($this->notificationService->unreadCount($request->user(), $this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function read(Request $request, int $id): JsonResponse
    {
        try {
            $notification = $this->notificationService->markRead($request->user(), $this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Marked as read.',
                'data'    => new NotificationResource($notification),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function readAll(Request $request): JsonResponse
    {
        try {
            $this->notificationService->markAllRead($request->user(), $workspace = $this->currentWorkspace($request));

            return response()->json([
                'message' => 'All notifications are marked as read.',
                'data'    => new UnreadNotificationsResource($this->notificationService->unreadCount($request->user(), $workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
