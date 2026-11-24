<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SendEcommercePaymentLinkRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Http\Resources\Ecommerce\EcommercePaymentLinkReviewResource;
use App\Services\Api\Ecommerce\EcommercePaymentLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An online order's payment links (Doc 19 Phase F6a), under Confirm Orders:
 * what sending one would ask, sending it - e-mailed to the customer - and
 * revoking one still active.
 */
class EcommercePaymentLinkController extends Controller
{
    public function __construct(
        protected EcommercePaymentLinks $links,
    ) {}

    public function review(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => new EcommercePaymentLinkReviewResource($this->links->review($this->currentWorkspace($request), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function send(SendEcommercePaymentLinkRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->links->send($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json(['message' => "A payment link for order {$order->number} is on its way to {$order->customer['email']}.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function revoke(Request $request, int $id, int $link): JsonResponse
    {
        try {
            $order = $this->links->revoke($this->currentWorkspace($request), $request->user(), $id, $link);

            return response()->json(['message' => "The payment link for order {$order->number} is revoked.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
