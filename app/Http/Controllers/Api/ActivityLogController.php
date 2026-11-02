<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Security\ListActivityLogRequest;
use App\Http\Resources\Security\ActivityLogResource;
use App\Http\Resources\Security\ActivityLogUserResource;
use App\Services\Api\Security\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Security & Audit > Activity Log (Doc 11 section 24): the
 * business's activity a page at a time, and who appears in it.
 */
class ActivityLogController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function index(ListActivityLogRequest $request): JsonResponse
    {
        try {
            $logs = $this->auditLogService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => ActivityLogResource::collection($logs->items()),
                'pagination' => slim_pagination($logs),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function users(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => ActivityLogUserResource::collection($this->auditLogService->users($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
