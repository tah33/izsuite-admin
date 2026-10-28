<?php

namespace App\Http\Resources\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The Dashboard (Doc 02): the range it covers, Sales Lite's cards, chart,
 * outstanding invoices and recent transactions - null while Sales is off -
 * and the payment accounts' balances at the end of the range. Totals are in
 * the base currency; each listed record in its own. Wraps
 * DashboardService::summary().
 */
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sales = $this->resource['sales'];

        return [
            'from'     => $this->resource['from'],
            'to'       => $this->resource['to'],
            'as_of'    => $this->resource['as_of'],
            'sales'    => $sales ? [
                'net_sales'         => $sales['net_sales'],
                'invoices'          => $sales['invoices'],
                'received'          => $sales['received'],
                'outstanding'       => $sales['outstanding'],
                'outstanding_count' => $sales['outstanding_count'],
                'overdue'           => $sales['overdue'],
                'overdue_count'     => $sales['overdue_count'],
                'chart'             => $sales['chart'],
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
                'recent'            => array_map(fn (array $row) => array_diff_key($row, ['created' => true]), $sales['recent']),
            ] : null,
            'accounts' => $this->resource['accounts'],
        ];
    }
}
