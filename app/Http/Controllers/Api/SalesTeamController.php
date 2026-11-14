<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\SaveSalesTeamRequest;
use App\Http\Resources\Sales\SalesOptionsResource;
use App\Http\Resources\Sales\SalesTeamResource;
use App\Services\Api\Sales\SalesTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sales teams (Doc 16 Phase A): Settings → Sales lists and edits them; the sales form chooses a salesperson and team. */
class SalesTeamController extends Controller
{
    public function __construct(
        protected SalesTeamService $teams,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => SalesTeamResource::collection($this->teams->index($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveSalesTeamRequest $request): JsonResponse
    {
        try {
            $team = $this->teams->save($this->currentWorkspace($request), null, $request->validated());

            return response()->json(['message' => 'Sales team added.', 'data' => new SalesTeamResource($team)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveSalesTeamRequest $request, int $id): JsonResponse
    {
        try {
            $team = $this->teams->save($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Sales team saved.', 'data' => new SalesTeamResource($team)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->teams->delete($this->currentWorkspace($request), $id, $request->user());

            return response()->json(['message' => 'Sales team deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The salespeople and teams the sales form chooses from. */
    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new SalesOptionsResource($this->teams->options($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
