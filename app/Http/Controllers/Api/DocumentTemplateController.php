<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Documents\SaveDocumentTemplateRequest;
use App\Http\Resources\Documents\DocumentTemplateResource;
use App\Services\Api\Documents\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Settings > Documents > Templates (Doc 06 section 14, Doc 11 section 20):
 * how each kind of document looks, addressed by its type -
 * /templates/invoice - and a PDF preview of settings before they are saved.
 */
class DocumentTemplateController extends Controller
{
    public function __construct(
        protected DocumentTemplateService $documentTemplateService,
    ) {}

    /** Each kind of document of the apps the business has on. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => DocumentTemplateResource::collection($this->documentTemplateService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveDocumentTemplateRequest $request, string $type): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $row       = $this->documentTemplateService->save($workspace, $this->documentTemplateService->type($workspace, $type), $request->validated());

            return response()->json([
                'message' => 'Template saved successfully.',
                'data'    => new DocumentTemplateResource($row),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A sample document drawn with the settings sent, as a PDF - nothing is saved. */
    public function preview(SaveDocumentTemplateRequest $request, string $type): Response
    {
        try {
            $workspace        = $this->currentWorkspace($request);
            [$filename, $pdf] = $this->documentTemplateService->preview($workspace, $this->documentTemplateService->type($workspace, $type), $request->validated());

            return $this->pdfResponse($filename, $pdf);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
