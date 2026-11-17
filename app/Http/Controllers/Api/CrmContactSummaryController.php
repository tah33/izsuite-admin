<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Crm\CrmContactSummaryResource;
use App\Services\Api\Crm\CrmContactSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The CRM part of a contact's profile (Doc 17 Phase F4): where they came from, and their opportunities. */
class CrmContactSummaryController extends Controller
{
    public function __construct(
        protected CrmContactSummaryService $summaryService,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmContactSummaryResource($this->summaryService->summary($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
