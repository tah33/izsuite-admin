<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\Expenses\ExpensePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * An expense's record - /expenses/12/pdf - and an expense payment's voucher
 * - /expenses/payments/7/pdf - to preview, print or, with ?download=1, save.
 */
class ExpensePdfController extends Controller
{
    public function __construct(
        protected ExpensePdfService $expensePdfService,
    ) {}

    public function expense(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->expensePdfService->expense($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function voucher(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->expensePdfService->voucher($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
