<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Models\User\SalesPayment;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Sales\SalesPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A customer payment's or refund's attachments (Doc 08 section 13): a bank
 * slip, a cheque's photo, the withholding certificate - counted toward the
 * business's storage, downloaded through GET /files/{id}/link.
 */
class SalesPaymentAttachmentController extends Controller
{
    public function __construct(
        protected SalesPaymentService $salesPaymentService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $payment = $this->salesPaymentService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($payment, FileCollection::Attachments)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(UploadSalesAttachmentsRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $payment   = $this->salesPaymentService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $payment, FileCollection::Attachments, $request->uploads(), $request->field(), $this->record($payment));

            return response()->json([
                'message' => $files->count() === 1 ? 'Attachment added.' : "{$files->count()} attachments added.",
                'data'    => WorkspaceFileResource::collection($files),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id, int $fileId): JsonResponse
    {
        try {
            $payment = $this->salesPaymentService->find($this->currentWorkspace($request), $id);
            $file    = $this->recordFileService->find($payment, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($payment, FileCollection::Attachments, $file, $this->record($payment));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** How the activity log names it. */
    private function record(SalesPayment $payment): string
    {
        return $payment->isRefund() ? "refund #{$payment->id}" : "payment {$payment->number}";
    }
}
