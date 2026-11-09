<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The card provider's own page to go to (Doc 13 Phase D2): to pay an
 * invoice by card, or to save a card.
 *
 * @property string $resource its address
 */
class CheckoutLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['url' => $this->resource];
    }
}
