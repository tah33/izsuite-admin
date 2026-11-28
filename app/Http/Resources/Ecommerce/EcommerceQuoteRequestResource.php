<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request for a quotation, whole (Doc 19 Phase H4a): its number and when
 * it came, its storefront, the customer - with their phone and company -
 * every product with how many, the delivery location, the date wanted and
 * the message.
 *
 * @property EcommerceQuoteRequest $resource
 */
class EcommerceQuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'id'          => $quote->id,
            'number'      => $quote->number,
            'received_at' => $quote->created_at?->toIso8601String(),
            'storefront'  => $quote->storefront?->name,
            'customer'    => [
                'name'    => $quote->customer_name,
                'email'   => $quote->customer_email,
                'phone'   => $quote->customer_phone,
                'company' => $quote->company,
                'account' => $quote->storefront_customer_id !== null,
            ],
            'items'       => $quote->items->map(fn (EcommerceQuoteRequestItem $item) => ['title' => $item->title, 'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.')])->values(),
            'delivery'    => implode(', ', array_filter([$quote->delivery_address, $quote->delivery_city, Countries::name($quote->delivery_country)])),
            'required_on' => $quote->required_on?->toDateString(),
            'message'     => $quote->message,
        ];
    }
}
