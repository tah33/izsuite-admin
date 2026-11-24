<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What recording an online order's payment would do (Doc 19 Phase F3), for
 * its Record Payment dialog: whether it can be and what stands in the way,
 * what the order owes, the account the money goes into and by which method,
 * the customer's reference, the days it may be dated - and, while the order
 * is pending, what confirming it first would do (as Confirm Order's dialog
 * shows it). EcommerceOrderPayments::review() gives it.
 */
class EcommerceOrderPaymentReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $order  = $review['order'];

        return [
            'ready'        => $review['problems'] === [],
            'problems'     => $review['problems'],
            'balance'      => $review['balance'],
            'currency'     => $order->currency,
            'account'      => $review['account']?->name,
            'method'       => $order->payment_snapshot['name'] ?? null,
            'reference'    => $order->payment_reference,
            'today'        => $review['today'],
            'from'         => $review['from'],
            'confirmation' => $review['confirmation'] ? new EcommerceOrderConfirmationResource($review['confirmation']) : null,
        ];
    }
}
