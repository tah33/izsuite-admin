<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ConvertSalesDocumentRequest;
use App\Http\Resources\Sales\SalesDocumentResource;
use App\Services\Api\Sales\SalesConversionService;
use Illuminate\Http\JsonResponse;

/**
 * POST /sales/{kind}/{id}/convert: a quotation into a proforma invoice or an
 * invoice, or a proforma into an invoice (see SalesConversionService). It
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
