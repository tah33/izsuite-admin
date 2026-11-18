<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The Sales Overview's figures (Doc 16 section 16.5): each a count and value, in the currency it names. */
class SalesOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from'                => $this->resource['from'],
            'to'                  => $this->resource['to'],
            'currency'            => $this->resource['currency'],
            'quotations'          => $this->resource['quotations'],
            'accepted_quotations' => $this->resource['accepted_quotations'],
            // Sales orders, apart from sales too (Doc 16 Phase B).
            'confirmed_orders'    => $this->resource['confirmed_orders'],
            'orders_to_deliver'   => $this->resource['orders_to_deliver'],
            'orders_to_invoice'   => $this->resource['orders_to_invoice'],
            'sales'               => $this->resource['sales'],
            'received'            => $this->resource['received'],
            'outstanding'         => $this->resource['outstanding'],
            'overdue'             => $this->resource['overdue'],
            'credit_notes'        => $this->resource['credit_notes'],
        ];
    }
}
