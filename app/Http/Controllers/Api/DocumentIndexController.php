<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Documents\ListDocumentsRequest;
use App\Http\Resources\Documents\DocumentIndexResource;
use App\Services\Api\Documents\DocumentIndexService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Documents page (Doc 06 sections 4 and 9): the business's transaction
 * documents as one list, a page at a time, and the same list as CSV.
 */
class DocumentIndexController extends Controller
{
    public function __construct(
        protected DocumentIndexService $documentIndexService,
    ) {}

    public function index(ListDocumentsRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $documents = $this->documentIndexService->list($workspace, $request->validated(), requested_per_page(25));
            $today     = $this->documentIndexService->today($workspace);
            $sources   = $this->documentIndexService->sources($workspace);

            return response()->json([
                'data'       => array_map(fn ($document) => new DocumentIndexResource($document, $today, $sources), $documents->items()),
                'pagination' => slim_pagination($documents),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(ListDocumentsRequest $request): StreamedResponse
    {
        try {
            return $this->documentIndexService->export($this->currentWorkspace($request), $request->validated());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
