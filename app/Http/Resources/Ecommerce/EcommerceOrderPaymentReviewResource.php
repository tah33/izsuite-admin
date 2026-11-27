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
 * shows it). From Phase F6b, the amount to start from - what its payment
 * link asks while its customer reported paying through it - and that report.
 * EcommerceOrderPayments::review() gives it.
 */
class EcommerceOrderPaymentReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;
        $order  = $review['order'];
        $report = $review['report'];

        return [
            'ready'        => $review['problems'] === [],
            'problems'     => $review['problems'],
            'balance'      => $review['balance'],
            'amount'       => $review['amount'],
            'currency'     => $order->currency,
            'account'      => $review['account']?->name,
            'method'       => $review['method'],
            'reference'    => $review['reference'],
            'report'       => $report ? ['at' => $report->reported_at->toIso8601String(), 'method' => $report->reported_method['name'] ?? null, 'reference' => $report->reported_reference] : null,
            'today'        => $review['today'],
            'from'         => $review['from'],
            'confirmation' => $review['confirmation'] ? new EcommerceOrderConfirmationResource($review['confirmation']) : null,
        ];
    }
}
