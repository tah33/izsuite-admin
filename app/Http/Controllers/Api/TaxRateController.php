<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Taxes\SaveTaxRateRequest;
use App\Http\Resources\Taxes\TaxRateResource;
use App\Services\Api\Taxes\TaxRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Taxes > Tax Rates (Doc 11 section 10). */
class TaxRateController extends Controller
{
    public function __construct(
        protected TaxRateService $taxRateService,
    ) {}

    /** The business's tax rates, active ones first. Not paginated: a business has a handful. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => TaxRateResource::collection($this->taxRateService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveTaxRateRequest $request): JsonResponse
    {
        try {
            $rate = $this->taxRateService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Tax rate added successfully.',
                'data'    => new TaxRateResource($rate),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveTaxRateRequest $request, int $id): JsonResponse
    {
        try {
            $rate = $this->taxRateService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => 'Tax rate updated successfully.',
                'data'    => new TaxRateResource($rate),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->taxRateService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Tax rate deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
