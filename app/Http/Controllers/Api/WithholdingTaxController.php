<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Taxes\SaveWithholdingTaxRequest;
use App\Http\Resources\Taxes\WithholdingTaxResource;
use App\Services\Api\Taxes\WithholdingTaxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Taxes > Withholding Tax (Doc 11 section 12). */
class WithholdingTaxController extends Controller
{
    public function __construct(
        protected WithholdingTaxService $withholdingTaxService,
    ) {}

    /** The business's withholding tax rates, active ones first. Not paginated: a business has a handful. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => WithholdingTaxResource::collection($this->withholdingTaxService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveWithholdingTaxRequest $request): JsonResponse
    {
        try {
            $rate = $this->withholdingTaxService->create($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Withholding tax added successfully.',
                'data'    => new WithholdingTaxResource($rate),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveWithholdingTaxRequest $request, int $id): JsonResponse
    {
        try {
            $rate = $this->withholdingTaxService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => 'Withholding tax updated successfully.',
                'data'    => new WithholdingTaxResource($rate),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->withholdingTaxService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Withholding tax deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
