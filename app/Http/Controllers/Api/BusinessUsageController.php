<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessSetup\BusinessUsageResource;
use App\Services\User\Workspaces\PlanLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Usage and Limits (Doc 13 sections 13.11 and 13.14): the business's package,
 * how much of its storage it uses, and how many users and locations it has of
 * what the package gives it. Apps join it when their limits do.
 */
class BusinessUsageController extends Controller
{
    public function __construct(
        protected PlanLimits $planLimits,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);

            return response()->json([
                'data' => new BusinessUsageResource($workspace, $this->planLimits->usage($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
