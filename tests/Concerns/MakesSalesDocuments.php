<?php

namespace Tests\Concerns;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\TaxRate;

/**
 * For tests of Sales Lite's documents: a business that uses taxes, with VAT
 * at 15%, a customer on 30 days' terms and an office chair - and the invoice
 * most tests start from:
 *
 *   2 chairs at 150.00, less 10%   270.00 + VAT 40.50
 *   delivery, no tax                25.00
 *   subtotal 295.00, tax 40.50, total 335.50
 *
 * Expects $this->user signed in.
 */
trait MakesSalesDocuments
{
    protected Contact $customer;

    protected Item $chair;

    protected TaxRate $vat;

    protected function setUpSales(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['tax_mode' => 'enabled'])->save();

        $this->vat      = $business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        $this->customer = $business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders', 'billing_city' => 'Dhaka', 'billing_country' => 'BD', 'customer_payment_terms' => 30]);
        $this->chair    = $business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Office Chair', 'unit' => 'Piece', 'sale_price' => '150.00', 'sales_tax_id' => $this->vat->id]);
    }

    protected function invoice(array $fields = []): array
    {
        return [
            'contact_id'    => $this->customer->id,
            'document_date' => '2026-10-28',
            'currency'      => 'USD',
            'lines'         => [
                ['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '2', 'unit' => 'Piece', 'unit_price' => '150.00', 'discount_type' => 'percent', 'discount_value' => '10', 'tax_rate_id' => $this->vat->id],
                ['name' => 'Delivery', 'quantity' => '1', 'unit_price' => '25.00'],
            ],
            ...$fields,
        ];
    }

    protected function quotation(array $fields = []): array
    {
        return $this->invoice(['expiry_date' => '2026-11-27', ...$fields]);
    }

    /** A draft of $kind ("invoices", "quotations", "proformas") - its id. */
    protected function draft(string $kind, array $fields = []): int
    {
        $payload = $kind === 'invoices' ? $this->invoice($fields) : $this->quotation($fields);

        return $this->postJson("/api/v1/sales/{$kind}", $payload)->assertCreated()->json('data.id');
    }
}
