<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\ReorderCrmCustomFieldsRequest;
use App\Http\Requests\Api\Crm\SaveCrmCustomFieldRequest;
use App\Http\Resources\Crm\CrmCustomFieldResource;
use App\Services\Api\Crm\CrmCustomFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → CRM's custom fields for leads and opportunities (Doc 17 Phase I4): listed, added, changed, deleted and put in order. */
class CrmCustomFieldController extends Controller
{
    public function __construct(
        protected CrmCustomFieldService $fieldService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => CrmCustomFieldResource::collection($this->fieldService->index($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCrmCustomFieldRequest $request): JsonResponse
    {
        try {
            $field = $this->fieldService->create($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Field added.', 'data' => new CrmCustomFieldResource($field)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCrmCustomFieldRequest $request, int $id): JsonResponse
    {
        try {
            $field = $this->fieldService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Field saved.', 'data' => new CrmCustomFieldResource($field)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->fieldService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Field deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reorder(ReorderCrmCustomFieldsRequest $request): JsonResponse
    {
        try {
            $fields = $this->fieldService->reorder($this->currentWorkspace($request), $request->validated('record'), $request->validated('ids'));

            return response()->json(['message' => 'Order saved.', 'data' => CrmCustomFieldResource::collection($fields)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
