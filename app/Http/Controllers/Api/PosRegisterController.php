<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SavePosRegisterRequest;
use App\Http\Resources\Pos\PosRegisterOptionsResource;
use App\Http\Resources\Pos\PosRegisterResource;
use App\Services\Api\Pos\PosRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POS registers (Doc 18 Phase A2): the Registers page, what a register's form chooses from, and adding, changing and deleting one. */
class PosRegisterController extends Controller
{
    public function __construct(
        protected PosRegisterService $registerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => PosRegisterResource::collection($this->registerService->index($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosRegisterOptionsResource($this->registerService->options($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePosRegisterRequest $request): JsonResponse
    {
        try {
            $register = $this->registerService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Register added.', 'data' => new PosRegisterResource($register)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePosRegisterRequest $request, int $id): JsonResponse
    {
        try {
            $register = $this->registerService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Register saved.', 'data' => new PosRegisterResource($register)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->registerService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Register deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
