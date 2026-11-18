<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\CrmOverviewRequest;
use App\Http\Resources\Crm\CrmOverviewResource;
use App\Services\Api\Crm\CrmOverviewService;
use Illuminate\Http\JsonResponse;

/** The CRM Overview's figures (Doc 17 section 17.4 - Phase G1). */
class CrmOverviewController extends Controller
{
    public function __construct(
        protected CrmOverviewService $overview,
    ) {}

    public function show(CrmOverviewRequest $request): JsonResponse
    {
        try {
            $figures = $this->overview->figures($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json(['data' => new CrmOverviewResource($figures)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
