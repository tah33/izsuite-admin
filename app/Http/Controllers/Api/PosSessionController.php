<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ClosePosSessionRequest;
use App\Http\Requests\Api\Pos\ListPosSessionsRequest;
use App\Http\Requests\Api\Pos\OpenPosSessionRequest;
use App\Http\Resources\Pos\PosSessionListResource;
use App\Http\Resources\Pos\PosSessionOptionsResource;
use App\Http\Resources\Pos\PosSessionResource;
use App\Services\Api\Pos\PosSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POS sessions (Doc 18 Phase B1): the Sessions page and its choices, opening one, a session's page, and closing it. */
class PosSessionController extends Controller
{
    public function __construct(
        protected PosSessionService $sessionService,
    ) {}

    public function index(ListPosSessionsRequest $request): JsonResponse
    {
        try {
            $sessions = $this->sessionService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => PosSessionListResource::collection($sessions->items()),
                'pagination' => slim_pagination($sessions),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosSessionOptionsResource($this->sessionService->options($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(OpenPosSessionRequest $request): JsonResponse
    {
        try {
            $page = $this->sessionService->open($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => "Session {$page['session']->number} opened.", 'data' => new PosSessionResource($page)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => new PosSessionResource($this->sessionService->show($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function close(ClosePosSessionRequest $request, int $id): JsonResponse
    {
        try {
            $page = $this->sessionService->close($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => "Session {$page['session']->number} closed.", 'data' => new PosSessionResource($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
