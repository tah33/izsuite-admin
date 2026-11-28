<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Models\User\WorkspaceFile;
use App\Support\Countries;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request for a quotation, whole (Doc 19 Phase H4a): its number and when
 * it came, its storefront, the customer - with their phone and company -
 * every product with how many, the delivery location, the date wanted and
 * the message - and (Phase H4d) the files that came with it.
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
            // Phase H4e: a request for a quotation, or a message from the contact page - its products, delivery and files none.
            'kind'        => $quote->kind,
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
            // Phase H4b: the CRM lead it became, if it did and the user may open it (EcommerceQuoteRequestService).
            'lead_id'     => $quote->getAttribute('shown_lead_id'),
            // Phase H4c: the quotation it became - {id, number}, none while a draft - if it did and the user may open it.
            'quotation'   => $quote->getAttribute('shown_quotation'),
            // Phase H4d: the files that came with it, to download through their links.
            'files'       => $quote->files->map(fn (WorkspaceFile $file) => ['id' => $file->id, 'name' => $file->name, 'size' => (int) $file->size])->values(),
        ];
    }
}
