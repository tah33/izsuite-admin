<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\CardPayments;
use App\Services\Billing\Payments\InvalidNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stripe's webhook (Doc 13 Phase D2, Doc 15 section 15): a finished
 * checkout, taken only with Stripe's signature, settled once however often
 * it comes. Anything else is acknowledged and left. A failure answers an
 * error, so Stripe sends it again later.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        protected CardPayments $cardPayments,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $this->cardPayments->notified($request->getContent(), $request->header('Stripe-Signature'));

            return response()->json(['message' => 'Received.']);

        } catch (InvalidNotification $e) {
            return response()->json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
