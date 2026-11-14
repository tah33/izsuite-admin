<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A row of the Returns page (Doc 16 Phase E): what the row shows. Expects order, its contact, delivery and location loaded. */
class SalesReturnListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'number'      => $this->number,
            'status'      => $this->status,
            'return_date' => $this->return_date,
            'order'       => ['id' => $this->sales_document_id, 'number' => $this->order?->number],
            'delivery'    => $this->delivery?->number,
            'customer'    => $this->order?->customer_snapshot['name'] ?? $this->order?->contact?->name,
            'location'    => $this->location?->name,
        ];
    }
}