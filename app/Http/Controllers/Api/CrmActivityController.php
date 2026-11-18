<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CompleteCrmActivityRequest;
use App\Http\Requests\Api\Crm\CrmActivityRegardingRequest;
use App\Http\Requests\Api\Crm\ListCrmActivitiesRequest;
use App\Http\Requests\Api\Crm\SaveCrmActivityRequest;
use App\Http\Resources\Crm\CrmActivityOptionsResource;
use App\Http\Resources\Crm\CrmActivityRegardingResource;
use App\Http\Resources\Crm\CrmActivityResource;
use App\Services\Api\Crm\CrmActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRM activities (Doc 17 Phase E1): the Activities page, what an activity's
 * form chooses from, what one can be for, a record's activities, and
 * planning, changing, completing and cancelling one.
 */
class CrmActivityController extends Controller
{
    public function __construct(
        protected CrmActivityService $activityService,
    ) {}

    public function index(ListCrmActivitiesRequest $request): JsonResponse
    {
        try {
            $activities = $this->activityService->list($this->currentWorkspace($request), $request->user(), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => CrmActivityResource::collection($activities->items()),
                'pagination' => slim_pagination($activities),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmActivityOptionsResource($this->activityService->options($this->currentWorkspace($request), $request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function regarding(CrmActivityRegardingRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmActivityRegardingResource($this->activityService->regarding($this->currentWorkspace($request), $request->user(), $request->validated('kind'), $request->validated('search'))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function forRecord(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => CrmActivityResource::collection($this->activityService->forRecord($this->currentWorkspace($request), $request->user(), $kind, $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCrmActivityRequest $request): JsonResponse
    {
        try {
            $activity = $this->activityService->create($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json(['message' => 'Activity planned.', 'data' => new CrmActivityResource($activity)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmActivityRequest $request, int $id): JsonResponse
    {
        try {
            $activity = $this->activityService->update($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json(['message' => 'Activity saved.', 'data' => new CrmActivityResource($activity)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function complete(CompleteCrmActivityRequest $request, int $id): JsonResponse
    {
        try {
            $done = $this->activityService->complete($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => $done['follow_up'] ? 'Activity completed, and its follow-up planned.' : 'Activity completed.',
                'data'    => new CrmActivityResource($done['activity']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $activity = $this->activityService->cancel($this->currentWorkspace($request), $request->user(), $id);

            return response()->json(['message' => 'Activity cancelled.', 'data' => new CrmActivityResource($activity)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
