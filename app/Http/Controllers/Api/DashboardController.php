<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Dashboard\DashboardRequest;
use App\Http\Resources\Dashboard\DashboardResource;
use App\Services\Api\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;

/** The Billing Starter Dashboard (Doc 02) for a range and a location: /dashboard?range=this_month. */
class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    public function __invoke(DashboardRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new DashboardResource($this->dashboardService->summary($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
