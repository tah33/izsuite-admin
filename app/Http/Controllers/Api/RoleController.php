<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Roles\SaveRoleRequest;
use App\Http\Resources\Roles\PermissionGroupResource;
use App\Http\Resources\Roles\RoleListResource;
use App\Http\Resources\Roles\RoleResource;
use App\Services\Api\Roles\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's roles (Doc 12 sections 10 to 15): listed, viewed, added,
 * changed, copied, switched off or on, and deleted while nobody holds them -
 * and the permissions a role can hold among the apps on now.
 */
class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roleService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => RoleListResource::collection($this->roleService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function permissions(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => PermissionGroupResource::collection($this->roleService->permissions($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new RoleResource($this->roleService->show($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->roleService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => "Role \"{$role->name}\" added.",
                'data'    => new RoleResource($role),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveRoleRequest $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => "Role \"{$role->name}\" saved.",
                'data'    => new RoleResource($role),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function copy(Request $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->copy($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "Role copied as \"{$role->name}\".",
                'data'    => new RoleListResource($role),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->activate($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "Role \"{$role->name}\" switched on.",
                'data'    => new RoleListResource($role),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        try {
            $role = $this->roleService->deactivate($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "Role \"{$role->name}\" switched off.",
                'data'    => new RoleListResource($role),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->roleService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Role deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
