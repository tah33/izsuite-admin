<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommercePaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What sending an online order a payment link would ask (Doc 19 Phase F6a):
 * what the order owes, in its currency - all of it, or at most, with paying
 * in part on - the days a link works to start, to whom it goes, and the
 * store's methods it may offer, the order's own first.
 * EcommercePaymentLinks::review() gives it.
 */
class EcommercePaymentLinkReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $review = $this->resource;

        return [
            'owed'     => $review['owed'],
            'currency' => $review['order']->currency,
            'partial'  => $review['partial'],
            'days'     => $review['days'],
            'to'       => $review['to'],
            'methods'  => $review['methods']->map(fn (EcommercePaymentMethod $method) => ['id' => $method->id, 'name' => $method->name])->values(),
        ];
    }
}
