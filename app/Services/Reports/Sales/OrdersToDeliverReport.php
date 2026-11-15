<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Orders to Deliver (Doc 16 section 16.26 - Phase H3b): each open sales order
 * with goods still to deliver - not or partly delivered, as the Dashboard
 * counts it - the earliest expected day first and those without one last.
 * Days late run from the expected day to today. Each row opens its order.
 */
class OrdersToDeliverReport extends OrderReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base  = $workspace->base_currency;
        $today = $filters['as_of'];
        $rows  = [];

        foreach ($this->sales->openOrders($workspace, $filters) as $order) {
            if (! in_array($order->delivery_status, ['not_delivered', 'partially_delivered'], true)) {
                continue;
            }

            $rows[] = [
                'number'                 => $order->number,
                'document_date'          => $order->document_date,
                'expected_delivery_date' => $order->expected_delivery_date,
                'days_late'              => $this->figures->daysOverdue($order->expected_delivery_date, $today),
                'customer'               => $this->figures->customerName($order),
                'salesperson'            => $order->salesperson?->name,
                'status'                 => $this->label($order->status->value),
                'delivery_status'        => $this->label($order->delivery_status),
                'to_deliver'             => $this->left($order)['to_deliver'],
                'link'                   => "/sales/orders/{$order->id}",
            ];
        }

        usort($rows, fn (array $a, array $b) => [$a['expected_delivery_date'] === null, $a['expected_delivery_date'], $a['document_date']]
            <=> [$b['expected_delivery_date'] === null, $b['expected_delivery_date'], $b['document_date']]);

        return new ReportTable(
            title: 'Orders to Deliver',
            period: 'Open on '.$this->day($today, $filters),
            columns: [
                $this->column('number', 'Order'),
                $this->column('document_date', 'Date', 'date'),
                $this->column('expected_delivery_date', 'Expected Delivery', 'date'),
                $this->column('days_late', 'Days Late', 'number'),
                $this->column('customer', 'Customer'),
                $this->column('salesperson', 'Salesperson'),
                $this->column('status', 'Status'),
                $this->column('delivery_status', 'Delivery'),
                $this->column('to_deliver', 'Left to Deliver', 'money', $base),
            ],
            rows: $rows,
            totals: ['number' => 'Total', ...$this->totals($rows, ['to_deliver'])],
            notes: ["Confirmed and held orders with goods still to deliver, as they stand now. Left to deliver is before tax, in {$base}."],
        );
    }
}
