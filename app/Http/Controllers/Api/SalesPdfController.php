<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Services\Api\Sales\SalesPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A sales document's PDF - /sales/invoices/12/pdf - and a payment's receipt -
 * /sales/payments/7/pdf - to preview, print or, with ?download=1, save.
 */
class SalesPdfController extends Controller
{
    public function __construct(
        protected SalesPdfService $salesPdfService,
    ) {}

    public function document(Request $request, string $kind, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->salesPdfService->document($this->currentWorkspace($request), DocumentType::fromKind($kind), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function receipt(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->salesPdfService->receipt($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
