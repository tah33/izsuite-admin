<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\SalesOverviewRequest;
use App\Http\Resources\Sales\SalesOverviewResource;
use App\Services\Api\Sales\SalesOverviewService;
use Illuminate\Http\JsonResponse;

/** The Sales Overview (Doc 16 section 16.5), for the full Sales app. */
class SalesOverviewController extends Controller
{
    public function __construct(
        protected SalesOverviewService $overview,
    ) {}

    public function show(SalesOverviewRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => new SalesOverviewResource($this->overview->figures($this->currentWorkspace($request), $request->validated()))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
