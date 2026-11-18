<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer's credit control and exposure (Doc 16 section 16.21 - Phase
 * G1), in the base currency: their settings; what their invoices still owe,
 * the overdue part of it, what their orders have not invoiced yet (while
 * those count) and the credit they hold unapplied; and what that leaves
 * against their limit - null without one.
 *
 * @property array $resource as CustomerCredit makes it
 */
class CustomerCreditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency'       => $this->resource['currency'],
            'credit_limit'   => $this->resource['credit_limit'],
            'credit_sales'   => $this->resource['credit_sales'],
            'credit_action'  => $this->resource['credit_action'],
            'credit_warning' => $this->resource['credit_warning'],
            'counts_orders'  => $this->resource['counts_orders'],
            'outstanding'    => $this->resource['outstanding'],
            'overdue'        => $this->resource['overdue'],
            'orders'         => $this->resource['orders'],
            'unapplied'      => $this->resource['unapplied'],
            'exposure'       => $this->resource['exposure'],
            'available'      => $this->resource['available'],
            'over'           => $this->resource['over'],
        ];
    }
}
