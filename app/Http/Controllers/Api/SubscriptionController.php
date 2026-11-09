<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Billing\ChangePlanRequest;
use App\Http\Requests\Api\Billing\KeepWithinLimitsRequest;
use App\Http\Requests\Api\Billing\SubscribeRequest;
use App\Http\Requests\Api\Billing\UpdateBillingDetailsRequest;
use App\Http\Resources\Billing\BillingDetailsResource;
use App\Http\Resources\Billing\OverLimitChoicesResource;
use App\Http\Resources\Billing\PlanChangePreviewResource;
use App\Http\Resources\Billing\SubscriptionOverviewResource;
use App\Http\Resources\Billing\SubscriptionQuoteResource;
use App\Http\Resources\BusinessSetup\BusinessUsageResource;
use App\Services\Api\Billing\BillingService;
use App\Services\Api\Billing\OverLimitService;
use App\Services\Api\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Apps & Subscription's billing side (Doc 13 sections 11 and 14),
 * for roles with Manage Subscription: the subscription, subscribing to a paid
 * plan and withdrawing an invoice not yet paid, and the billing details -
 * and, over a plan's limits, for the Business Owner to choose what stays
 * active.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
        protected OverLimitService $overLimitService,
        protected SubscriptionService $subscriptionService,
    ) {}

    /** A paid plan, monthly or yearly: its trial starts, or its invoice is issued. */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->subscribe($this->currentWorkspace($request), $request->user(), $request->validated('package'), $request->validated('cycle'));

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** What a paid plan would come to, its tax included, before subscribing. */
    public function quote(SubscribeRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SubscriptionQuoteResource($this->subscriptionService->quote($this->currentWorkspace($request), $request->validated('package'), $request->validated('cycle'))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** What moving to another plan or cycle would cost, and when. */
    public function previewChange(ChangePlanRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new PlanChangePreviewResource($this->subscriptionService->previewChange($this->currentWorkspace($request), $request->validated('package'), $request->validated('cycle'))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Moves up at once - its invoice issued - or down when the paid period ends. */
    public function change(ChangePlanRequest $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->change($this->currentWorkspace($request), $request->user(), $request->validated('package'), $request->validated('cycle'));

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Undoes a move down before the period ends. */
    public function undoChange(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->undoChange($this->currentWorkspace($request), $request->user());

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Cancels the subscription: at the end of its paid period - or at once, on a trial or in grace. */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->cancel($this->currentWorkspace($request), $request->user());

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Undoes a cancellation before the period ends. */
    public function resume(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->resume($this->currentWorkspace($request), $request->user());

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The invoice waiting for the first payment withdrawn, and the subscription with it. */
    public function withdraw(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->withdraw($this->currentWorkspace($request), $request->user());

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SubscriptionOverviewResource($this->billingService->overview($this->currentWorkspace($request), $request->user())),
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

    /** The users and locations over the plan's limits, for the owner to choose from. */
    public function overLimit(Request $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);

            return response()->json([
                'data' => new OverLimitChoicesResource($workspace, $this->overLimitService->choices($workspace, $request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Keeps those chosen active, and switches the rest off; answers with the usage now. */
    public function keepWithinLimits(KeepWithinLimitsRequest $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $kept      = $this->overLimitService->keep($workspace, $request->user(), $request->validated('member_ids', []), $request->validated('location_ids', []));

            return response()->json([
                'message' => $kept['message'],
                'data'    => new BusinessUsageResource($workspace, $kept['usage']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
