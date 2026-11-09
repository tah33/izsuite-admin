<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Billing\SaveTransferReferenceRequest;
use App\Http\Resources\Billing\SubscriptionInvoiceResource;
use App\Services\Api\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * izSuite's invoices to the business for its subscription (Doc 13 section
 * 11's Billing History): the list, the transfer reference on one still to be
 * paid, and each as a PDF - its receipt once paid. For roles with Manage
 * Subscription.
 */
class SubscriptionInvoiceController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $invoices = $this->subscriptionService->history($this->currentWorkspace($request), 20);

            return response()->json([
                'data'       => SubscriptionInvoiceResource::collection($invoices->getCollection()),
                'pagination' => slim_pagination($invoices),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reference(SaveTransferReferenceRequest $request, int $id): JsonResponse
    {
        try {
            $invoice = $this->subscriptionService->saveReference($this->currentWorkspace($request), $id, $request->validated('transfer_reference'));

            return response()->json([
                'message' => $invoice->transfer_reference ? 'Transfer reference saved.' : 'Transfer reference removed.',
                'data'    => new SubscriptionInvoiceResource($invoice),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** ?receipt=1 for its receipt; ?download=1 to save it rather than open it. */
    public function pdf(Request $request, int $id): Response
    {
        try {
            [$name, $pdf] = $this->subscriptionService->pdf($this->currentWorkspace($request), $id, $request->boolean('receipt'));

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')."; filename={$name}",
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
