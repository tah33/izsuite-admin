<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\SendDocumentEmailRequest;
use App\Http\Resources\Sales\EmailDraftResource;
use App\Http\Resources\Sales\SalesDocumentResource;
use App\Http\Resources\Sales\SalesPaymentResource;
use App\Services\Api\Sales\DocumentEmailService;
use App\Services\Api\Sales\SalesDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Send by Email and Send Reminder (Doc 06 section 15, Doc 11 section 22): a
 * sales document's or receipt's email form - /sales/invoices/12/email-draft,
 * ?reminder=1 for a reminder - and sending it. Both answer with the record,
 * its email history included.
 */
class SalesEmailController extends Controller
{
    public function __construct(
        protected DocumentEmailService $documentEmailService,
        protected SalesDocumentService $salesDocumentService,
    ) {}

    public function draft(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $draft = $this->documentEmailService->documentDraft($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->boolean('reminder'));

            return response()->json([
                'data' => new EmailDraftResource($draft),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function send(SendDocumentEmailRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $document  = $this->documentEmailService->sendDocument($workspace, DocumentType::fromKind($kind), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => $request->boolean('reminder') ? 'Reminder on its way.' : 'Email on its way.',
                'data'    => new SalesDocumentResource($document, $this->salesDocumentService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function receiptDraft(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new EmailDraftResource($this->documentEmailService->receiptDraft($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function sendReceipt(SendDocumentEmailRequest $request, int $id): JsonResponse
    {
        try {
            $payment = $this->documentEmailService->sendReceipt($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Email on its way.',
                'data'    => new SalesPaymentResource($payment),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
