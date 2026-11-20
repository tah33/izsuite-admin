<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\EmailPosReceiptRequest;
use App\Http\Resources\Pos\PosReceiptResource;
use App\Services\Api\Pos\PosReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A POS sale's receipt (Doc 18 Phase C4): its PDF - /pos/orders/12/receipt,
 * to print or, with ?download=1, save - each copy counted, and e-mailed.
 */
class PosReceiptController extends Controller
{
    public function __construct(
        protected PosReceiptService $receiptService,
    ) {}

    public function show(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->receiptService->pdf($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function email(EmailPosReceiptRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->receiptService->email($this->currentWorkspace($request), $id, $request->validated());

            return response()->json([
                'message' => "Receipt {$order->number} is on its way to {$request->validated('to')}.",
                'data'    => new PosReceiptResource($order),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
