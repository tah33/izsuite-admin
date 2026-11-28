<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A message from a storefront's contact page as the Messages page lists it
 * (Doc 19 Phase H4e): its number and when it came, its storefront, the
 * customer, the start of the message, and the CRM lead it became for a user
 * who may open it. The whole message comes from its own address.
 *
 * @property EcommerceQuoteRequest $resource
 */
class EcommerceMessageListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id'          => $message->id,
            'number'      => $message->number,
            'received_at' => $message->created_at?->toIso8601String(),
            'storefront'  => $message->storefront?->name,
            'customer'    => ['name' => $message->customer_name, 'email' => $message->customer_email, 'account' => $message->storefront_customer_id !== null],
            'message'     => Str::limit((string) $message->message, 140),
            'lead_id'     => $message->getAttribute('shown_lead_id'),
        ];
    }
}
