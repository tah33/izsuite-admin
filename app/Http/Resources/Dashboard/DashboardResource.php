<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Dashboard (Doc 02): the range it covers, Sales Lite's cards and
 * outstanding invoices - null while Sales is off - the Purchases card - null
 * while Purchases is off - the Transaction Overview, with a series for each
 * app on, the newest transactions of both, and the payment accounts'
 * balances at the end of the range. Totals are in the base currency; each
 * listed record in its own. Wraps DashboardService::summary().
 */
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sales = $this->resource['sales'];

        return [
            'from'      => $this->resource['from'],
            'to'        => $this->resource['to'],
            'as_of'     => $this->resource['as_of'],
            'sales'     => $sales ? [
                'net_sales'         => $sales['net_sales'],
                'invoices'          => $sales['invoices'],
                'received'          => $sales['received'],
                'outstanding'       => $sales['outstanding'],
                'outstanding_count' => $sales['outstanding_count'],
                'overdue'           => $sales['overdue'],
                'overdue_count'     => $sales['overdue_count'],
                'outstanding_rows'  => array_map(fn (array $row) => [
                    'id'             => $row['id'],
                    'number'         => $row['number'],
                    'customer'       => $row['customer'],
                    'due_date'       => $row['due_date'],
                    'outstanding'    => $row['outstanding'],
                    'currency'       => $row['currency'],
                    'payment_status' => $row['payment_status'],
                    'overdue'        => $row['days_overdue'] > 0,
                ], $sales['outstanding_rows']),
            ] : null,
            'purchases' => $this->resource['purchases'],
            'chart'     => $this->resource['chart'],
            'recent'    => array_map(fn (array $row) => array_diff_key($row, ['created' => true]), $this->resource['recent']),
            'accounts'  => $this->resource['accounts'],
        ];
    }
}
