<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request for a quotation as the Quote Requests page lists it (Doc 19
 * Phase H4a): its number and when it came, its storefront, the customer, its
 * first two products and how many it holds, where it is delivered and the
 * date wanted. The whole request comes from its own address.
 *
 * @property EcommerceQuoteRequest $resource
 */
class EcommerceQuoteRequestListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quote = $this->resource;

        return [
            'id'          => $quote->id,
            'number'      => $quote->number,
            'received_at' => $quote->created_at?->toIso8601String(),
            'storefront'  => $quote->storefront?->name,
            'customer'    => ['name' => $quote->customer_name, 'email' => $quote->customer_email, 'account' => $quote->storefront_customer_id !== null],
            'items'       => $quote->items->take(2)->map(fn (EcommerceQuoteRequestItem $item) => ['title' => $item->title, 'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.')])->values(),
            'lines'       => $quote->items->count(),
            'delivery'    => implode(', ', array_filter([$quote->delivery_city, Countries::name($quote->delivery_country)])),
            'required_on' => $quote->required_on?->toDateString(),
            // Phase H4b: the CRM lead it became, if it did and the user may open it (EcommerceQuoteRequestService).
            'lead_id'     => $quote->getAttribute('shown_lead_id'),
        ];
    }
}
