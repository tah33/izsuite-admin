<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ConvertSalesDocumentRequest;
use App\Http\Requests\Api\Sales\CreateDepositRequest;
use App\Http\Requests\Api\Sales\InvoiceSalesOrderRequest;
use App\Http\Resources\Sales\SalesDocumentResource;
use App\Services\Api\Sales\SalesConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /sales/{kind}/{id}/convert: a quotation into a proforma invoice, a
 * sales order or an invoice, a proforma into a sales order or an invoice, or
 * a proforma made from a sales order (see SalesConversionService). POST
 * /sales/{kind}/{id}/duplicate copies a sales order as a new draft. Each
 * answers with the new draft.
 */
class SalesConversionController extends Controller
{
    public function __construct(
        protected SalesConversionService $salesConversionService,
    ) {}

    public function store(ConvertSalesDocumentRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $from      = DocumentType::fromKind($kind);
            $to        = DocumentType::from($request->validated('to'));
            $document  = $this->salesConversionService->convert($workspace, $from, $id, $to, $request->boolean('again'), $request->user());

            return response()->json([
                'message' => $this->made($to, $from, $document->source?->number),
                'data'    => new SalesDocumentResource($document, $this->salesConversionService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function duplicate(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $type      = DocumentType::fromKind($kind);
            $document  = $this->salesConversionService->duplicate($workspace, $type, $id, $request->user());

            return response()->json([
                'message' => "Draft {$this->noun($type)} made as a copy.",
                'data'    => new SalesDocumentResource($document, $this->salesConversionService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A draft invoice of a sales order's lines, some or all of them (Doc 16 Phase B2). */
    public function invoice(InvoiceSalesOrderRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $document  = $this->salesConversionService->invoiceOrder($workspace, $id, $request->quantities(), $request->user(), $request->validated('reason'));

            return response()->json([
                'message' => $this->made(DocumentType::Invoice, DocumentType::SalesOrder, $document->source?->number),
                'data'    => new SalesDocumentResource($document, $this->salesConversionService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A draft deposit invoice of a confirmed sales order (Doc 16 Phase D2). */
    public function deposit(CreateDepositRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $document  = $this->salesConversionService->depositOrder($workspace, $id, $request->validated(), $request->user());

            return response()->json([
                'message' => "Draft deposit invoice made from sales order {$document->source?->number}.",
                'data'    => new SalesDocumentResource($document, $this->salesConversionService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    // "Draft invoice made from quotation QUO-00001." - or from "the draft
    // quotation", for one converted before it was numbered.
    private function made(DocumentType $to, DocumentType $from, ?string $number): string
    {
        return "Draft {$this->noun($to)} made from ".($number ? "{$this->noun($from)} {$number}" : "the draft {$this->noun($from)}").'.';
    }

    private function noun(DocumentType $type): string
    {
        return strtolower($type->label());
    }
}
