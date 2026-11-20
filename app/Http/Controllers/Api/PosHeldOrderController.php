<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\CancelPosHeldOrderRequest;
use App\Http\Requests\Api\Pos\HoldPosOrderRequest;
use App\Http\Resources\Pos\PosHeldCartResource;
use App\Http\Resources\Pos\PosHeldOrderResource;
use App\Services\Api\Pos\PosHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders held at the user's POS register (Doc 18 Phase D1): the list, a cart
 * held, one as resuming would load it - what changed since - resumed, and
 * cancelled.
 */
class PosHeldOrderController extends Controller
{
    public function __construct(
        protected PosHoldService $holdService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => PosHeldOrderResource::collection($this->holdService->list($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(HoldPosOrderRequest $request): JsonResponse
    {
        try {
            $held = $this->holdService->hold($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => "Order {$held->number} held.",
                'data'    => new PosHeldOrderResource($held),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => new PosHeldCartResource($this->holdService->preview($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        try {
            $resumed = $this->holdService->resume($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "Order {$resumed['held']->number} resumed.",
                'data'    => new PosHeldCartResource($resumed),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancel(CancelPosHeldOrderRequest $request, int $id): JsonResponse
    {
        try {
            $held = $this->holdService->cancel($this->currentWorkspace($request), $id, trim($request->validated('reason')));

            return response()->json(['message' => "Order {$held->number} cancelled."]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
