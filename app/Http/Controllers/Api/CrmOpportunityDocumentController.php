<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Crm\CrmOpportunityDocumentResource;
use App\Services\Crm\OpportunityDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The sales documents made from an opportunity (Doc 17 Phase F1), on its
 * page: those of the kinds the user views, at the locations they may use.
 */
class CrmOpportunityDocumentController extends Controller
{
    public function __construct(
        protected OpportunityDocuments $opportunityDocuments,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => CrmOpportunityDocumentResource::collection($this->opportunityDocuments->list($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
