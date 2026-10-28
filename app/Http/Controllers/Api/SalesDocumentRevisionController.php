<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\SalesDocumentRevisionResource;
use App\Services\Api\Sales\SalesRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A quotation's or proforma's earlier versions (Doc 08 section 12), read-only. */
class SalesDocumentRevisionController extends Controller
{
    public function __construct(
        protected SalesRevisionService $salesRevisionService,
    ) {}

    public function index(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => SalesDocumentRevisionResource::collection($this->salesRevisionService->list($this->currentWorkspace($request), DocumentType::fromKind($kind), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, string $kind, int $id, int $revision): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SalesDocumentRevisionResource($this->salesRevisionService->find($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $revision)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
