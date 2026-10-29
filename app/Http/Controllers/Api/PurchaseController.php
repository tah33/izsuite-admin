<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchases\ListPurchasesRequest;
use App\Http\Requests\Api\Purchases\SavePurchaseRequest;
use App\Http\Resources\Purchases\PurchaseListResource;
use App\Http\Resources\Purchases\PurchaseResource;
use App\Services\Api\Purchases\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Purchases Lite's purchases (Doc 09): the list, one purchase whole, a draft
 * added, changed or deleted, and the list as CSV.
 */
class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
    ) {}

    public function index(ListPurchasesRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $purchases = $this->purchaseService->list($workspace, $request->validated(), requested_per_page());
            $today     = $this->purchaseService->today($workspace);

            return response()->json([
                'data'       => array_map(fn ($purchase) => new PurchaseListResource($purchase, $today), $purchases->items()),
                'pagination' => slim_pagination($purchases),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(ListPurchasesRequest $request): StreamedResponse
    {
        try {
            return $this->purchaseService->export($this->currentWorkspace($request), $request->validated());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePurchaseRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $purchase  = $this->purchaseService->create($workspace, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Draft purchase saved.',
                'data'    => new PurchaseResource($purchase, $this->purchaseService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** One purchase, whole - for its page and its edit form. */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);

            return response()->json([
                'data' => new PurchaseResource($this->purchaseService->find($workspace, $id), $this->purchaseService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePurchaseRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $purchase  = $this->purchaseService->update($workspace, $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Purchase saved.',
                'data'    => new PurchaseResource($purchase, $this->purchaseService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Only a draft is deleted; a recorded purchase is voided instead. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->purchaseService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Draft purchase deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
