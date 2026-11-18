<?php

namespace App\Services\Reports\Sales;

use App\Models\User\Item;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Services\Reports\Report;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * What the order reports share (Doc 16 section 16.26 - Phase H3b): an open
 * sales order - confirmed or on hold, as the Dashboard counts it - as it
 * stands now. Its value is before tax in the base currency. Left to deliver
 * is each goods line's share for what is not delivered yet; left to invoice,
 * each line's share for what its ordered quantity has not been invoiced yet.
 */
abstract class OrderReport extends Report
{
    /** @return array{value: string, to_deliver: string, to_invoice: string} $order's value, and what is left of it to deliver and to invoice */
    protected function left(SalesDocument $order): array
    {
        $left = ['value' => '0.00', 'to_deliver' => '0.00', 'to_invoice' => '0.00'];

        foreach ($order->lines as $line) {
            $net = $this->figures->lineNetOf($order, $line);

            $left['value']      = $this->add($left['value'], $net);
            $left['to_invoice'] = $this->add($left['to_invoice'], $this->unreached($net, $line, 'invoiced_quantity'));

            // Goods, not services, as SalesOrderProgress::needsDelivery() has it.
            if ($line->item?->type !== Item::TYPE_SERVICE) {
                $left['to_deliver'] = $this->add($left['to_deliver'], $this->unreached($net, $line, 'delivered_quantity'));
            }
        }

        return $left;
    }

    /** A status as the report shows it: "On Hold", "Partially Delivered". */
    protected function label(?string $status): string
    {
        return Str::headline((string) $status);
    }

    /** The share of a line's $net that its $done quantity has not reached. */
    private function unreached(string $net, SalesDocumentLine $line, string $done): string
    {
        $quantity = Decimal::of($line->quantity);
        $left     = bcsub($quantity, Decimal::of($line->{$done}), 3);

        return Decimal::isZero($quantity) || bccomp($left, '0', 3) <= 0 ? '0.00' : Decimal::round(bcdiv(bcmul($net, $left, 10), $quantity, 10));
    }
}
