<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ListSalesDocumentsRequest;
use App\Http\Requests\Api\Sales\SaveSalesDocumentRequest;
use App\Http\Resources\Sales\SalesDocumentListResource;
use App\Http\Resources\Sales\SalesDocumentResource;
use App\Services\Api\Sales\SalesDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sales Lite's quotations, proforma invoices and invoices (Doc 08), addressed
 * by their kind: /sales/quotations, /sales/proformas, /sales/invoices. A new
 * one is a draft; issuing, sending and the rest are SalesDocumentFlowController's.
 */
class SalesDocumentController extends Controller
{
    public function __construct(
        protected SalesDocumentService $salesDocumentService,
    ) {}

    /** A page of the kind's documents, newest first, searched and filtered. */
    public function index(ListSalesDocumentsRequest $request, string $kind): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $documents = $this->salesDocumentService->list($workspace, DocumentType::fromKind($kind), $request->validated(), requested_per_page());
            $today     = $this->salesDocumentService->today($workspace);

            return response()->json([
                'data'       => array_map(fn ($document) => new SalesDocumentListResource($document, $today), $documents->items()),
                'pagination' => slim_pagination($documents),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveSalesDocumentRequest $request, string $kind): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $type      = DocumentType::fromKind($kind);
            $document  = $this->salesDocumentService->create($workspace, $type, $request->user(), $request->validated());

            return response()->json([
                'message' => "Draft {$this->noun($type)} saved.",
                'data'    => new SalesDocumentResource($document, $this->salesDocumentService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** One document, whole - for its page and its edit form. */
    public function show(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $document  = $this->salesDocumentService->find($workspace, DocumentType::fromKind($kind), $id);

            return response()->json([
                'data' => new SalesDocumentResource($document, $this->salesDocumentService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveSalesDocumentRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $type      = DocumentType::fromKind($kind);
            $document  = $this->salesDocumentService->update($workspace, $type, $id, $request->user(), $request->validated());

            return response()->json([
                'message' => ucfirst($this->noun($type)).' saved.',
                'data'    => new SalesDocumentResource($document, $this->salesDocumentService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Only a draft is deleted; anything numbered is cancelled or voided instead. */
    public function destroy(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $type = DocumentType::fromKind($kind);

            $this->salesDocumentService->delete($this->currentWorkspace($request), $type, $id);

            return response()->json([
                'message' => "Draft {$this->noun($type)} deleted.",
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function noun(DocumentType $type): string
    {
        return strtolower($type->label());
    }
}
