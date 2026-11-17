<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\SaveCrmEmailTemplateRequest;
use App\Http\Resources\Crm\CrmEmailTemplateResource;
use App\Services\Api\Crm\CrmEmailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → CRM's e-mail templates (Doc 17 Phase F3b): added, changed and deleted. Settings → CRM lists them. */
class CrmEmailTemplateController extends Controller
{
    public function __construct(
        protected CrmEmailTemplateService $templateService,
    ) {}

    public function store(SaveCrmEmailTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templateService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Template added.', 'data' => new CrmEmailTemplateResource($template)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmEmailTemplateRequest $request, int $id): JsonResponse
    {
        try {
            $template = $this->templateService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Template saved.', 'data' => new CrmEmailTemplateResource($template)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->templateService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Template deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
