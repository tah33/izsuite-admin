<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ApplyCreditRequest;
use App\Http\Requests\Api\Sales\SalesStatusRequest;
use App\Http\Resources\Sales\CreditChoiceResource;
use App\Http\Resources\Sales\SalesAllocationResource;
use App\Services\Api\Sales\SalesAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Applying credit to invoices (Doc 16 Phase D1): what an invoice can take,
 * where an advance or credit note can go, credit applied, and credit taken
 * off again.
 */
class SalesAllocationController extends Controller
{
    public function __construct(
        protected SalesAllocationService $salesAllocationService,
    ) {}

    /** GET /sales/invoices/{id}/credit: the customer's advances and other credit notes it can take. */
    public function sources(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => CreditChoiceResource::collection($this->salesAllocationService->sourcesFor($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** GET /sales/advances/{id}/invoices: the customer's invoices still owing in its currency. */
    public function advanceTargets(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => CreditChoiceResource::collection($this->salesAllocationService->invoicesForAdvance($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** GET /sales/credit-notes/{id}/invoices: the customer's other invoices still owing in its currency. */
    public function noteTargets(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => CreditChoiceResource::collection($this->salesAllocationService->invoicesForNote($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** POST /sales/invoices/{id}/allocations */
    public function store(ApplyCreditRequest $request, int $id): JsonResponse
    {
        try {
            $allocation = $this->salesAllocationService->apply($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => "Applied {$allocation->source_label} to invoice {$allocation->invoice->number}.",
                'data'    => new SalesAllocationResource($allocation),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** POST /sales/allocations/{id}/remove */
    public function remove(SalesStatusRequest $request, int $id): JsonResponse
    {
        try {
            $allocation = $this->salesAllocationService->remove($this->currentWorkspace($request), $request->user(), $id, $request->validated('reason'));

            return response()->json([
                'message' => "Took {$allocation->source_label} off invoice {$allocation->invoice->number}.",
                'data'    => new SalesAllocationResource($allocation),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
