<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notifications\AnnouncementResource;
use App\Services\Shared\Announcements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Platform announcements (Doc 15 section 15.21 - Phase F1): the banners izSuite shows the business's people, each put away by a person for themselves. */
class AnnouncementController extends Controller
{
    public function __construct(
        protected Announcements $announcements,
    ) {}

    /** Those the signed-in user sees in the business now, the most urgent first. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => AnnouncementResource::collection($this->announcements->for($this->currentWorkspace($request), $request->user()))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** One put away by the user, for themselves - unless it is not dismissible. */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        try {
            $this->announcements->dismiss($id, $request->user(), $this->currentWorkspace($request));

            return response()->json(['message' => 'Dismissed.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
