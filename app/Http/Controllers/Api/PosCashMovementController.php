<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\DeclinePosPaymentRequest;
use App\Http\Requests\Api\Pos\ListPosCashMovementsRequest;
use App\Http\Requests\Api\Pos\RecordPosCashMovementRequest;
use App\Http\Resources\Pos\PosCashMovementListResource;
use App\Http\Resources\Pos\PosCashMovementOptionsResource;
use App\Http\Resources\Pos\PosCashMovementResource;
use App\Models\User\PosCashMovement;
use App\Services\Api\Pos\PosCashMovementService;
use App\Services\Api\Pos\PosRecordLists;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POS cash movements (Doc 18 Phase F1): what recording one starts from, one recorded, a session's, and one waiting approved or declined. */
class PosCashMovementController extends Controller
{
    public function __construct(
        protected PosCashMovementService $movements,
        protected PosRecordLists $lists,
    ) {}

    /** Phase G1: the page of movements. */
    public function list(ListPosCashMovementsRequest $request): JsonResponse
    {
        try {
            $page = $this->lists->movements($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json(['data' => PosCashMovementListResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function options(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosCashMovementOptionsResource($this->movements->options($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(RecordPosCashMovementRequest $request): JsonResponse
    {
        try {
            $movement = $this->movements->record($this->currentWorkspace($request), $request->validated());
            $named    = $this->movements->named($movement);

            return response()->json([
                'message' => $movement->status === PosCashMovement::PENDING ? "{$named} waits for a manager's approval: nothing moves until then." : "{$named} recorded.",
                'data'    => new PosCashMovementResource($movement),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => PosCashMovementResource::collection($this->movements->of($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $movement = $this->movements->approve($this->currentWorkspace($request), $id);

            return response()->json(['message' => $this->movements->named($movement).' approved: the cash has moved.', 'data' => new PosCashMovementResource($movement)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function decline(DeclinePosPaymentRequest $request, int $id): JsonResponse
    {
        try {
            $movement = $this->movements->decline($this->currentWorkspace($request), $id, $request->validated('reason'));

            return response()->json(['message' => $this->movements->named($movement).' declined: nothing moves.', 'data' => new PosCashMovementResource($movement)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
