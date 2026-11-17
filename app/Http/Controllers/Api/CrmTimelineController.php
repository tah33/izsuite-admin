<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmTimelineRequest;
use App\Http\Resources\Crm\CrmTimelineEntryResource;
use App\Services\Api\Crm\CrmTimelineService;
use Illuminate\Http\JsonResponse;

/**
 * The timeline of a lead, an opportunity or a contact (Doc 17 Phase E3):
 * its activities done, notes, changes, files and a contact's sales
 * documents, newest first, or only one of them.
 */
class CrmTimelineController extends Controller
{
    public function __construct(
        protected CrmTimelineService $timelineService,
    ) {}

    public function __invoke(CrmTimelineRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $entries = $this->timelineService->timeline($this->currentWorkspace($request), $request->user(), $kind, $id, $request->validated('show') ?? 'all');

            return response()->json([
                'data' => CrmTimelineEntryResource::collection($entries),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
