<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\Purchases\PurchasePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A purchase's record - /purchases/12/pdf - and a supplier payment's voucher
 * - /purchases/payments/7/pdf - to preview, print or, with ?download=1, save.
 */
class PurchasePdfController extends Controller
{
    public function __construct(
        protected PurchasePdfService $purchasePdfService,
    ) {}

    public function purchase(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->purchasePdfService->purchase($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function voucher(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->purchasePdfService->voucher($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
