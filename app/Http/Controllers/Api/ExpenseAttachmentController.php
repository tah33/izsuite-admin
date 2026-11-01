<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSalesAttachmentsRequest;
use App\Http\Resources\Files\WorkspaceFileResource;
use App\Services\Api\Files\RecordFileService;
use App\Services\Api\Expenses\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An expense's attachments (Doc 10 section 5): the receipt - an image or a
 * PDF - and anything else kept with it, counted toward the business's
 * storage, downloaded through GET /files/{id}/link. Never read by OCR unless
 * that app is on.
 */
class ExpenseAttachmentController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService,
        protected RecordFileService $recordFileService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $expense = $this->expenseService->find($this->currentWorkspace($request), $id);

            return response()->json([
                'data' => WorkspaceFileResource::collection($this->recordFileService->list($expense, FileCollection::Attachments)),
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
            $expense  = $this->expenseService->find($workspace, $id);
            $files     = $this->recordFileService->add($workspace, $expense, FileCollection::Attachments, $request->uploads(), $request->field(), $this->expenseService->describe($expense));

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
            $expense = $this->expenseService->find($this->currentWorkspace($request), $id);
            $file     = $this->recordFileService->find($expense, FileCollection::Attachments, $fileId);

            $this->recordFileService->remove($expense, FileCollection::Attachments, $file, $this->expenseService->describe($expense));

            return response()->json([
                'message' => 'Attachment removed.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
