<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pos\PosFormalInvoiceResource;
use App\Services\Api\Pos\PosFormalInvoices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** A POS sale's formal invoice (Doc 18 Phase D4): its invoice given the business's invoice number, and that invoice's PDF - ?download=1 to save it. */
class PosFormalInvoiceController extends Controller
{
    public function __construct(
        protected PosFormalInvoices $formalInvoices,
    ) {}

    public function store(Request $request, int $id): JsonResponse
    {
        try {
            $order = $this->formalInvoices->issue($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => "{$order->number} is now invoice {$order->invoice->number}.",
                'data'    => new PosFormalInvoiceResource($order),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->formalInvoices->pdf($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
