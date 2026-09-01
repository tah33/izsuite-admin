<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plans\ListPlanRequest;
use App\Http\Resources\Plans\PlanResource;
use App\Services\Api\Plans\PlanService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    /**
     * List active plans.
     */
    public function index(ListPlanRequest $request): JsonResponse
    {
        try {
            $plans = $this->planService->list([
                'search'       => $request->query('search'),
                'billing_type' => $request->query('billing_type'),
                'plan_for'     => $request->query('plan_for'),
                // Absent or blank means "no filter"; present means filter on the boolean.
                'featured'     => $request->filled('featured') ? $request->boolean('featured') : null,
                'per_page'     => requested_per_page(),
            ]);

            return response()->json([
                'data'       => PlanResource::collection($plans->items()),
                'pagination' => slim_pagination($plans),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
