<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Workspaces\SaveWorkspaceRequest;
use App\Http\Resources\Workspaces\WorkspaceResource;
use App\Services\Api\Workspaces\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
    ) {}

    /**
     * The signed-in account's workspaces, alphabetical by name. Not paginated:
     * an account has a handful of them, and the Workspace page lists all.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => WorkspaceResource::collection($this->workspaceService->list($request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveWorkspaceRequest $request): JsonResponse
    {
        try {
            $workspace = $this->workspaceService->create($request->user(), $request->validated());

            return response()->json([
                'message' => 'Workspace added successfully.',
                'data'    => new WorkspaceResource($workspace),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveWorkspaceRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->workspaceService->update($request->user(), $id, $request->validated());

            return response()->json([
                'message' => 'Workspace updated successfully.',
                'data'    => new WorkspaceResource($workspace),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->workspaceService->delete($request->user(), $id);

            return response()->json([
                'message' => 'Workspace deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
