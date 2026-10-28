<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Files\UploadSignatureRequest;
use App\Http\Resources\Documents\DocumentTemplateResource;
use App\Services\Api\Documents\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A document template's signature or stamp image (Doc 06 section 14): one
 * picture, shown above the signature line. A new one replaces it. Both answer
 * with the template, so the page shows the change at once.
 */
class DocumentSignatureController extends Controller
{
    public function __construct(
        protected DocumentTemplateService $documentTemplateService,
    ) {}

    public function store(UploadSignatureRequest $request, string $type): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $row       = $this->documentTemplateService->addSignature($workspace, $this->documentTemplateService->type($workspace, $type), $request->uploads(), $request->field());

            return response()->json([
                'message' => 'Signature saved.',
                'data'    => new DocumentTemplateResource($row),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, string $type): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $row       = $this->documentTemplateService->removeSignature($workspace, $this->documentTemplateService->type($workspace, $type));

            return response()->json([
                'message' => 'Signature removed.',
                'data'    => new DocumentTemplateResource($row),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
