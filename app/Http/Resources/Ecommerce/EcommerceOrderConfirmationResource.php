<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What confirming an online order would do (Doc 19 Phase F2), for its
 * Confirm dialog: whether it can be, what stands in the way, what is pointed
 * out - each price the store sells at now that is not the order's, both as
 * the store shows prices - what it counts down, the invoice it would issue
 * (its day, contact and total) and to whom the e-mail goes.
 * EcommerceOrderConfirmation::review() gives it.
 */
class EcommerceOrderConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $review  = $this->resource;
        $order   = $review['order'];
        $contact = $review['contact'];

        return [
            'ready'    => $review['ready'],
            'problems' => $review['problems'],
            'notices'  => $review['notices'],
            'prices'   => $review['prices'],
            'stock'    => array_map(fn (array $stock) => ['title' => $stock['title'], 'left' => $stock['left'], 'after' => $stock['after']], $review['stock']),
            'invoice'  => [
                'date'     => $review['date'],
                'contact'  => "{$contact->name} ({$contact->contact_number})",
                'total'    => (string) $order->grand_total,
                'currency' => $order->currency,
            ],
            'email'    => $order->customer['email'] ?? null,
        ];
    }
}
