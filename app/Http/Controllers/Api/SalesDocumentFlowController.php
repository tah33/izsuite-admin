<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\IssueSalesDocumentRequest;
use App\Http\Requests\Api\Sales\SalesStatusRequest;
use App\Http\Resources\Sales\SalesDocumentResource;
use App\Models\User\SalesDocument;
use App\Services\Api\Sales\SalesDocumentFlow;
use App\Services\Api\Sales\SalesDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A sales document's status changes (see SalesDocumentFlow): issue an
 * invoice; send, accept, reject or cancel a quotation or proforma; void an
 * invoice. Each answers with the document as it is now.
 */
class SalesDocumentFlowController extends Controller
{
    public function __construct(
        protected SalesDocumentFlow $salesDocumentFlow,
        protected SalesDocumentService $salesDocumentService,
    ) {}

    public function issue(IssueSalesDocumentRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentFlow->issue($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user(), $request->validated('exchange_rate'));

            return $this->answer($request, $document, "{$document->type->label()} {$document->number} issued.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function send(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentFlow->send($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user());

            return $this->answer($request, $document, "{$document->type->label()} {$document->number} marked as sent.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function accept(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentFlow->accept($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user());

            return $this->answer($request, $document, "Quotation {$document->number} marked as accepted.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reject(SalesStatusRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentFlow->reject($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user(), $request->validated('reason'));

            return $this->answer($request, $document, "Quotation {$document->number} marked as rejected.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function cancel(SalesStatusRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentFlow->cancel($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user(), $request->validated('reason'));

            return $this->answer($request, $document, "{$document->type->label()} {$document->number} cancelled.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function void(SalesStatusRequest $request, string $kind, int $id): JsonResponse
    {
        try {
            $document = $this->salesDocumentFlow->void($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user(), $request->validated('reason'));

            return $this->answer($request, $document, "{$document->type->label()} {$document->number} voided.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The answer for every change, which each action catches around. */
    private function answer(Request $request, SalesDocument $document, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => new SalesDocumentResource($document, $this->salesDocumentService->today($this->currentWorkspace($request))),
        ]);
    }
}
