<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\CalculateSalesRequest;
use App\Http\Requests\Api\Sales\SalesDefaultsRequest;
use App\Http\Resources\Sales\SalesDefaultsResource;
use App\Http\Resources\Sales\SalesTotalsResource;
use App\Services\Api\Sales\SalesFormService;
use Illuminate\Http\JsonResponse;

/**
 * What the sales document form asks as it is filled in: what a new document
 * starts with, and the totals of what it holds (see SalesFormService).
 */
class SalesFormController extends Controller
{
    public function __construct(
        protected SalesFormService $salesFormService,
    ) {}

    public function defaults(SalesDefaultsRequest $request, string $kind): JsonResponse
    {
        try {
            $defaults = $this->salesFormService->defaults($this->currentWorkspace($request), DocumentType::fromKind($kind), $request->validated());

            return response()->json([
                'data' => new SalesDefaultsResource($defaults),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function calculate(CalculateSalesRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SalesTotalsResource($this->salesFormService->calculate($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
