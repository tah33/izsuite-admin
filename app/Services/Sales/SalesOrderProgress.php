<?php

namespace App\Services\Sales;

use App\Models\User\Item;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use Illuminate\Support\Collection;

/**
 * Where a sales order's delivery and invoicing stand (Doc 16 section 16.9),
 * from its lines' quantities - ordered (the line's quantity), delivered,
 * invoiced, returned and credited - kept on the order beside its own status.
 * Expects its lines loaded with their items (SalesDocumentRepository::loadLineItems).
 */
final class SalesOrderProgress
{
    /** Its delivery statuses, in the order they come about. */
    public const DELIVERY = ['not_required', 'not_delivered', 'partially_delivered', 'delivered', 'partially_returned', 'returned'];

    /** Its invoice statuses. */
    public const INVOICE = ['not_invoiced', 'partially_invoiced', 'invoiced'];

    /**
     * Not Required when no line is to be delivered - each is a service - and
     * otherwise from what was delivered and returned of the lines that are.
     */
    public function deliveryStatus(SalesDocument $order): string
    {
        $lines = $order->lines->filter(fn (SalesDocumentLine $line) => $this->needsDelivery($line));

        if ($lines->isEmpty()) {
            return 'not_required';
        }

        $delivered = $this->sum($lines, 'delivered_quantity');
        $returned  = $this->sum($lines, 'returned_quantity');

        return match (true) {
            bccomp($returned, '0', 3) > 0                         => bccomp($returned, $delivered, 3) >= 0 ? 'returned' : 'partially_returned',
            bccomp($delivered, '0', 3) <= 0                       => 'not_delivered',
            $this->every($lines, 'delivered_quantity')            => 'delivered',
            default                                               => 'partially_delivered',
        };
    }

    /** Not Invoiced, Partially Invoiced, or Invoiced once every line's ordered quantity was. */
    public function invoiceStatus(SalesDocument $order): string
    {
        return match (true) {
            bccomp($this->sum($order->lines, 'invoiced_quantity'), '0', 3) <= 0 => 'not_invoiced',
            $this->every($order->lines, 'invoiced_quantity')                  => 'invoiced',
            default                                                           => 'partially_invoiced',
        };
    }

    /** A line of goods, to be delivered: one whose item is not a service, or a description typed in. */
    public function needsDelivery(SalesDocumentLine $line): bool
    {
        return $line->item?->type !== Item::TYPE_SERVICE;
    }

    private function sum(Collection $lines, string $column): string
    {
        return $lines->reduce(fn (string $total, SalesDocumentLine $line) => bcadd($total, (string) $line->{$column}, 3), '0');
    }

    /** Whether $column reached every line's ordered quantity. */
    private function every(Collection $lines, string $column): bool
    {
        return $lines->every(fn (SalesDocumentLine $line) => bccomp((string) $line->{$column}, (string) $line->quantity, 3) >= 0);
    }
}
