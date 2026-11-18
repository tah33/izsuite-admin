<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmLeadDuplicateRequest;
use App\Http\Resources\Crm\CrmLeadDuplicateResource;
use App\Models\User\CrmDuplicateReview;
use App\Services\Api\Crm\CrmLeadDuplicateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A lead's possible duplicates (Doc 17 Phase C2): the list, and ignoring one
 * with a reason, linking one, or taking a decision back - each answering
 * with the list as it is after.
 */
class CrmLeadDuplicateController extends Controller
{
    public function __construct(
        protected CrmLeadDuplicateService $crmLeadDuplicateService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => CrmLeadDuplicateResource::collection($this->crmLeadDuplicateService->list($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function ignore(CrmLeadDuplicateRequest $request, int $id): JsonResponse
    {
        return $this->decided(fn () => $this->crmLeadDuplicateService->decide($this->currentWorkspace($request), $request->user(), $id, CrmDuplicateReview::IGNORED, $request->validated()), 'Marked as not a duplicate.');
    }

    public function link(CrmLeadDuplicateRequest $request, int $id): JsonResponse
    {
        return $this->decided(fn () => $this->crmLeadDuplicateService->decide($this->currentWorkspace($request), $request->user(), $id, CrmDuplicateReview::LINKED, $request->validated()), 'Linked.');
    }

    public function clear(CrmLeadDuplicateRequest $request, int $id): JsonResponse
    {
        return $this->decided(fn () => $this->crmLeadDuplicateService->clear($this->currentWorkspace($request), $request->user(), $id, $request->validated()), 'Decision taken back.');
    }

    private function decided(callable $decide, string $message): JsonResponse
    {
        try {
            return response()->json([
                'message' => $message,
                'data'    => CrmLeadDuplicateResource::collection($decide()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
