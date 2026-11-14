<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ListCustomerAdvancesRequest;
use App\Http\Requests\Api\Sales\RecordAdvanceRequest;
use App\Http\Requests\Api\Sales\SalesStatusRequest;
use App\Http\Resources\Sales\CustomerAdvanceListResource;
use App\Http\Resources\Sales\CustomerAdvanceResource;
use App\Services\Api\Sales\CustomerAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer advances (Doc 16 Phase D1): Payments Received's Advances and a
 * customer's, one advance, one recorded from a customer or sales order, and
 * one reversed. Applying them is SalesAllocationController's.
 */
class CustomerAdvanceController extends Controller
{
    public function __construct(
        protected CustomerAdvanceService $customerAdvanceService,
    ) {}

    public function index(ListCustomerAdvancesRequest $request): JsonResponse
    {
        try {
            $advances = $this->customerAdvanceService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => CustomerAdvanceListResource::collection($advances->items()),
                'pagination' => slim_pagination($advances),
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
                'data' => new CustomerAdvanceResource($this->customerAdvanceService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(RecordAdvanceRequest $request): JsonResponse
    {
        try {
            $advance = $this->customerAdvanceService->record($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => "Advance {$advance->number} recorded.",
                'data'    => new CustomerAdvanceResource($advance),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reverse(SalesStatusRequest $request, int $id): JsonResponse
    {
        try {
            $advance = $this->customerAdvanceService->reverse($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason'));

            return response()->json([
                'message' => "Advance {$advance->number} reversed.",
                'data'    => new CustomerAdvanceResource($advance),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
