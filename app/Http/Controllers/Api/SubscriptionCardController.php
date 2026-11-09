<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Billing\CompleteCardCheckoutRequest;
use App\Http\Resources\Billing\CheckoutLinkResource;
use App\Http\Resources\Billing\SubscriptionOverviewResource;
use App\Services\Api\Billing\SubscriptionCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paying izSuite by card (Doc 13 Phase D2), for roles with Manage
 * Subscription: the invoice to pay on Stripe's page, coming back from it,
 * and the saved card - added or replaced on Stripe's page, or removed.
 */
class SubscriptionCardController extends Controller
{
    public function __construct(
        protected SubscriptionCardService $cardService,
    ) {}

    /** Stripe's page to pay invoice {id} by card. */
    public function checkout(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CheckoutLinkResource($this->cardService->checkout($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Back from Stripe's page: what the checkout did, settled now if Stripe has not said yet. */
    public function complete(CompleteCardCheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->cardService->complete($this->currentWorkspace($request), $request->user(), $request->validated('session_id'));

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Invoice {id} paid with the saved card, now (Phase D3). */
    public function charge(Request $request, int $id): JsonResponse
    {
        try {
            $result = $this->cardService->chargeNow($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Stripe's page to save a card, or another in its place. */
    public function setup(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CheckoutLinkResource($this->cardService->cardSetup($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The saved card removed. */
    public function remove(Request $request): JsonResponse
    {
        try {
            $result = $this->cardService->removeCard($this->currentWorkspace($request), $request->user());

            return response()->json([
                'message' => $result['message'],
                'data'    => new SubscriptionOverviewResource($result['overview']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
