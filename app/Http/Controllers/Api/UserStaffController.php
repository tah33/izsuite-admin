<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserStaff\SaveUserStaffRequest;
use App\Http\Resources\UserStaff\UserStaffResource;
use App\Services\Api\UserStaff\UserStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserStaffController extends Controller
{
    public function __construct(
        protected UserStaffService $userStaffService,
    ) {}

    /**
     * The business's staff, alphabetical by name. Not paginated: the
     * Staff page lists all of them, like the Locations and Workspace pages do.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => UserStaffResource::collection($this->userStaffService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveUserStaffRequest $request): JsonResponse
    {
        try {
            $staff = $this->userStaffService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Staff member added successfully.',
                'data'    => new UserStaffResource($staff),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveUserStaffRequest $request, int $id): JsonResponse
    {
        try {
            $staff = $this->userStaffService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => 'Staff member updated successfully.',
                'data'    => new UserStaffResource($staff),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->userStaffService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Staff member deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
