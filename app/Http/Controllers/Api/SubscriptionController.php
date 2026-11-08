<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Billing\UpdateBillingDetailsRequest;
use App\Http\Resources\Billing\BillingDetailsResource;
use App\Http\Resources\Billing\SubscriptionOverviewResource;
use App\Services\Api\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Apps & Subscription's billing side (Doc 13 section 11), for roles with Manage Subscription. */
class SubscriptionController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SubscriptionOverviewResource($this->billingService->overview($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updateBillingDetails(UpdateBillingDetailsRequest $request): JsonResponse
    {
        try {
            $details = $this->billingService->saveDetails($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Billing details saved.',
                'data'    => new BillingDetailsResource($details),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
