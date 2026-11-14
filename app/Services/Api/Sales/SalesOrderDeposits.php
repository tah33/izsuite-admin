<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDocument;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * A sales order's deposit invoices (Doc 16 section 16.14). A deposit is an
 * invoice of the order (source_id) marked is_deposit, of a fixed amount or a
 * percentage of the order's total; it is paid like any invoice. The order's
 * later invoices deduct its issued deposits with a negative line each
 * (deposit_invoice_id), never more than a deposit has left, so nothing is
 * billed twice. What the order bills in all - deposits and the invoices net
 * of their deductions - stays within its total unless a user who may invoice
 * beyond the order gives a reason (Phase B2's permission).
 *
 * What a deposit has to deduct is its total in a tax-inclusive order, and its
 * total before tax otherwise, as the deduction line carries the deposit's tax
 * - none, as made here.
 */
class SalesOrderDeposits
{
    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected WorkspaceAccess $access,
    ) {}

    /** A deposit's amount: $value itself, or $value percent of the order's total, to the cent. */
    public function amountOf(SalesDocument $order, string $type, string $value): string
    {
        return $type === 'percent'
            ? Decimal::round(bcdiv(bcmul((string) $order->grand_total, $value, 10), '100', 10))
            : Decimal::round($value);
    }

    /**
     * Whether $amount more takes what the order bills past its total: refused
     * then, unless the user may invoice beyond the order and gave $reason.
     */
    public function checkValue(SalesDocument $order, string $amount, ?string $reason): bool
    {
        $billed = Decimal::sum($this->salesDocumentRepository->invoicesOfOrder($order)->pluck('grand_total'));

        if (bccomp(bcadd($billed, $amount, 2), (string) $order->grand_total, 2) <= 0) {
            return false;
        }

        if (! $this->access->can(SalesOrderInvoicing::BEYOND)) {
            throw ValidationException::withMessages(['value' => "The order's invoices already come to {$order->currency} {$billed} of its {$order->grand_total}. This would bill past its total."]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Say why the order is billed past its total.']);
        }

        return true;
    }

    /**
     * Whether issuing $invoice takes what an order with deposits bills past
     * its total: its issued invoices, deposits included, and $invoice. Each
     * invoice rounds its own tax, so a few cents over are let through.
     */
    public function billsPast(SalesDocument $order, SalesDocument $invoice): bool
    {
        $invoices = $this->salesDocumentRepository->invoicesOfOrder($order);

        if (! $invoices->contains(fn (SalesDocument $row) => $row->is_deposit)) {
            return false;
        }

        $billed = Decimal::sum($invoices->filter(fn (SalesDocument $row) => $row->status->value === 'issued' && $row->id !== $invoice->id)->pluck('grand_total'));

        // Read whole: an invoice's source comes with a few columns only, and a missing one reads as null.
        $total = $this->salesDocumentRepository->grandTotalOf($order);

        return bccomp(bcadd($billed, (string) $invoice->grand_total, 2), bcadd($total, '0.05', 2), 2) > 0;
    }

    /** What is left to deduct of $deposit: its deductible amount less its deductions on invoices not void, but $exceptId's. */
    public function leftOf(SalesDocument $deposit, ?int $exceptId = null): string
    {
        return Decimal::max(bcsub($this->deductible($deposit), $this->salesDocumentRepository->deductedOf($deposit, $exceptId), 2), '0.00');
    }

    /**
     * The lines a new invoice of $order takes to deduct its issued deposits:
     * one per deposit with something left, oldest first, up to $upTo - the
     * invoice's total before them. Positions follow $after.
     *
     * @return list<array<string, mixed>>
     */
    public function deductions(SalesDocument $order, string $upTo, int $after): array
    {
        $rows = [];

        foreach ($this->salesDocumentRepository->depositsOfOrder($order) as $deposit) {
            $take = Decimal::min($this->leftOf($deposit), $upTo);

            if (bccomp($take, '0', 2) <= 0) {
                continue;
            }

            $upTo   = bcsub($upTo, $take, 2);
            $rows[] = $this->line($deposit, $take, $after + count($rows) + 1);
        }

        return $rows;
    }

    /**
     * $rows of an invoice saved from its form, their deductions checked: only
     * an invoice made from a sales order deducts, only that order's issued
     * deposits, and never more than each has left. A deduction is one line of
     * quantity 1 with its deposit's tax, as it was made.
     *
     * @return list<array<string, mixed>>
     */
    public function checkLines(?SalesDocument $invoice, array $rows): array
    {
        $taking = array_filter($rows, fn (array $row) => ! empty($row['deposit_invoice_id']));

        if ($taking === []) {
            return $rows;
        }

        $order = $invoice?->type === DocumentType::Invoice && $invoice->source?->type === DocumentType::SalesOrder ? $invoice->source : null;

        if (! $order || $invoice->is_deposit) {
            throw ValidationException::withMessages(['lines' => 'A deposit is deducted only on a later invoice made from its sales order.']);
        }

        $deposits = $this->salesDocumentRepository->depositsOfOrder($order)->keyBy('id');
        $taken    = [];

        foreach ($rows as $key => $row) {
            if (empty($row['deposit_invoice_id'])) {
                continue;
            }

            $deposit = $deposits->get($row['deposit_invoice_id']);
            $amount  = Decimal::round(bcsub('0', bcmul((string) $row['quantity'], (string) $row['unit_price'], 10), 10));

            if (! $deposit) {
                throw ValidationException::withMessages(['lines' => "Line {$row['position']} deducts an invoice that is not an issued deposit of this order."]);
            }

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages(['lines' => "Line {$row['position']}: a deposit is deducted as a negative amount."]);
            }

            $taken[$deposit->id] = bcadd($taken[$deposit->id] ?? '0', $amount, 2);

            if (bccomp($taken[$deposit->id], $left = $this->leftOf($deposit, $invoice->id), 2) > 0) {
                throw ValidationException::withMessages(['lines' => "Only {$invoice->currency} {$left} of deposit {$deposit->number} is left to deduct."]);
            }

            $rows[$key] = [...$row, ...$this->line($deposit, $amount, (int) $row['position']), 'name' => $row['name'] ?: "Less deposit {$deposit->number}", 'description' => $row['description'] ?? null];
        }

        return $rows;
    }

    /** Before an invoice is issued: its deductions still within what each deposit has left - another invoice may have taken some since. */
    public function checkIssue(SalesDocument $invoice): void
    {
        $this->checkLines($invoice, $invoice->lines->map(fn ($line) => $line->only(['position', 'name', 'description', 'quantity', 'unit_price', 'deposit_invoice_id']))->all());
    }

    /** Whether a deposit invoice is deducted on an invoice not void: it is not voided then. */
    public function isDeducted(SalesDocument $deposit): bool
    {
        return bccomp($this->salesDocumentRepository->deductedOf($deposit), '0', 2) > 0;
    }

    /** A sales order's deposits for its page: each one's number, status, total and what is deducted of it. */
    public function forOrder(SalesDocument $order): array
    {
        return $this->salesDocumentRepository->invoicesOfOrder($order, withVoid: true)
            ->filter(fn (SalesDocument $invoice) => $invoice->is_deposit)
            ->map(fn (SalesDocument $deposit) => [
                'id'          => $deposit->id,
                'number'      => $deposit->number,
                'status'      => $deposit->status->value,
                'grand_total' => (string) $deposit->grand_total,
                'deducted'    => $this->salesDocumentRepository->deductedOf($deposit),
            ])
            ->values()
            ->all();
    }

    /** What a deposit has to deduct: its total in a tax-inclusive document, else its total before tax. */
    private function deductible(SalesDocument $deposit): string
    {
        return $deposit->price_tax_mode === 'inclusive' ? (string) $deposit->grand_total : bcsub((string) $deposit->grand_total, (string) $deposit->tax_total, 2);
    }

    /** A deduction line of $amount off $deposit, with the deposit's tax. */
    private function line(SalesDocument $deposit, string $amount, int $position): array
    {
        $first = $deposit->lines->first();

        return [
            'position'           => $position,
            'source_line_id'     => null,
            'deposit_invoice_id' => $deposit->id,
            'item_id'            => null,
            'name'               => "Less deposit {$deposit->number}",
            'description'        => null,
            'quantity'           => '1',
            'unit'               => null,
            'unit_price'         => bcsub('0', $amount, 2),
            'discount_type'      => null,
            'discount_value'     => null,
            'tax_rate_id'        => $first?->tax_rate_id,
            'tax_name'           => $first?->tax_name,
            'tax_code'           => $first?->tax_code,
            'tax_rate'           => $first?->tax_rate,
            'tax_treatment'      => $first?->tax_treatment,
            'components'         => null,
            'warranty'           => null,
        ];
    }
}
