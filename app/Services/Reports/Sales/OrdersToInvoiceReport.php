<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Orders to Invoice (Doc 16 section 16.26 - Phase H3b): each open sales
 * order not invoiced in full, as the Dashboard counts it, oldest first - its
 * value, what was invoiced and what is left (see OrderReport). Draft
 * invoices are not taken off. Each row opens its order.
 */
class OrdersToInvoiceReport extends OrderReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ($this->sales->openOrders($workspace, $filters) as $order) {
            if ($order->invoice_status === 'invoiced') {
                continue;
            }

            $left   = $this->left($order);
            $rows[] = [
                'number'         => $order->number,
                'document_date'  => $order->document_date,
                'customer'       => $this->figures->customerName($order),
                'salesperson'    => $order->salesperson?->name,
                'status'         => $this->label($order->status->value),
                'invoice_status' => $this->label($order->invoice_status ?? 'not_invoiced'),
                'value'          => $left['value'],
                'invoiced'       => $this->subtract($left['value'], $left['to_invoice']),
                'to_invoice'     => $left['to_invoice'],
                'link'           => "/sales/orders/{$order->id}",
            ];
        }

        return new ReportTable(
            title: 'Orders to Invoice',
            period: 'Open on '.$this->day($filters['as_of'], $filters),
            columns: [
                $this->column('number', 'Order'),
                $this->column('document_date', 'Date', 'date'),
                $this->column('customer', 'Customer'),
                $this->column('salesperson', 'Salesperson'),
                $this->column('status', 'Status'),
                $this->column('invoice_status', 'Invoicing'),
                $this->column('value', 'Order Value', 'money', $base),
                $this->column('invoiced', 'Invoiced', 'money', $base),
                $this->column('to_invoice', 'Left to Invoice', 'money', $base),
            ],
            rows: $rows,
            totals: ['number' => 'Total', ...$this->totals($rows, ['value', 'invoiced', 'to_invoice'])],
            notes: ["Confirmed and held orders not invoiced in full, as they stand now, before tax in {$base}. Left to invoice is what their ordered quantities have not been invoiced yet; draft invoices are not taken off."],
        );
    }
}
