<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ClosePosSessionRequest;
use App\Http\Requests\Api\Pos\ListPosSessionsRequest;
use App\Http\Requests\Api\Pos\OpenPosSessionRequest;
use App\Http\Requests\Api\Pos\ReopenPosSessionRequest;
use App\Http\Resources\Pos\PosSessionListResource;
use App\Http\Resources\Pos\PosSessionOptionsResource;
use App\Http\Resources\Pos\PosSessionResource;
use App\Models\User\PosSession;
use App\Services\Api\Pos\PosSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POS sessions (Doc 18 Phases B1 and B2a): the Sessions page and its choices,
 * opening one, a session's page, closing it, approving its variance and
 * reopening it.
 */
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

            $waits = $page['session']->status === PosSession::CLOSING ? ' Its variance waits for approval.' : '';

            return response()->json(['message' => "Session {$page['session']->number} closed.{$waits}", 'data' => new PosSessionResource($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $page = $this->sessionService->approve($this->currentWorkspace($request), $id);

            return response()->json(['message' => "Variance approved: session {$page['session']->number} is closed.", 'data' => new PosSessionResource($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase F3: its X report while it is open, or its Z report once counted - $kind x or z - as a PDF. */
    public function report(Request $request, int $id, string $kind): Response
    {
        try {
            [$filename, $pdf] = $this->sessionService->report($this->currentWorkspace($request), $id, $kind);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reopen(ReopenPosSessionRequest $request, int $id): JsonResponse
    {
        try {
            $page = $this->sessionService->reopen($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => "Session {$page['session']->number} reopened.", 'data' => new PosSessionResource($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
