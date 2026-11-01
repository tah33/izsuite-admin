<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\PurchaseStatus;
use App\Enums\SalesDocumentStatus;
use App\Models\User\PaymentAccount;
use App\Models\User\Purchase;
use App\Models\User\PurchaseLine;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * A made-up document for a template's preview (Doc 11 section 20): the
 * business as it is now, its next number, notes and terms, with a sample
 * customer and lines - never saved - so a template can be seen before the
 * business has documents of its own. Its lines use every column a template
 * can show; a business that uses taxes sees a sample tax.
 */
class DocumentSample
{
    /** name, description, quantity, unit, unit price, line discount, warranty, components */
    private const LINES = [
        ['Website design', 'Home page and five inner pages', '1', null, '1200.00', '0.00', null, []],
        ['Hosting', 'Business plan, billed monthly', '12', 'months', '25.00', '30.00', null, []],
        ['Laptop', '14-inch, 16 GB memory', '1', 'pcs', '950.00', '0.00', ['period' => 12, 'unit' => 'months'], [['name' => 'Charger', 'quantity' => 1], ['name' => 'Carry case', 'quantity' => 1]]],
    ];

    private const TAX_RATE = '10';

    public function __construct(
        protected SalesSnapshots $salesSnapshots,
        protected SalesSettingRepository $salesSettingRepository,
        protected DocumentNumbers $documentNumbers,
        protected BusinessCalendar $businessCalendar,
    ) {}

    /** A sample $type drawn with $template - the settings being tried. */
    public function sales(Workspace $workspace, DocumentType $type, array $template): SalesDocument
    {
        $today    = $this->businessCalendar->today($workspace);
        $settings = $this->salesSettingRepository->forWorkspace($workspace);
        $taxed    = $workspace->usesTaxes();
        $lines    = new Collection(array_map(fn (array $line) => $this->line($line, $taxed), self::LINES));
        $subtotal = Decimal::sum($lines->pluck('amount')->map(fn ($amount) => (string) $amount)->all());
        $tax      = Decimal::sum($lines->pluck('tax_amount')->map(fn ($amount) => (string) $amount)->all());
        $total    = bcadd($subtotal, $tax, 2);

        $document = (new SalesDocument)->forceFill([
            'id'                   => 0,
            'type'                 => $type,
            'number'               => $this->documentNumbers->preview($workspace, $this->documentNumbers->scheme($workspace, $type), $today),
            'status'               => $type->isOffer() ? SalesDocumentStatus::Sent : SalesDocumentStatus::Issued,
            'document_date'        => $today->toDateString(),
            'due_date'             => $type === DocumentType::Invoice ? $today->addDays(14)->toDateString() : null,
            'expiry_date'          => $type->isOffer() ? $today->addDays(30)->toDateString() : null,
            'currency'             => $workspace->base_currency,
            'exchange_rate'        => '1',
            'price_tax_mode'       => 'exclusive',
            'customer_reference'   => 'PO-2041',
            'subtotal'             => $subtotal,
            'discount_total'       => '0.00',
            'tax_total'            => $tax,
            'grand_total'          => $total,
            'base_grand_total'     => $total,
            'taxes'                => $taxed ? [['name' => 'Tax', 'rate' => self::TAX_RATE, 'treatment' => 'standard', 'tax_amount' => $tax]] : [],
            'payment_status'       => 'unpaid',
            'amount_paid'          => '0.00',
            'withholding_amount'   => '0.00',
            'credited_amount'      => '0.00',
            'refunded_amount'      => '0.00',
            'balance_due'          => $total,
            'reason'               => $type === DocumentType::CreditNote ? 'One month of hosting was not used.' : null,
            'notes'                => $settings->notes ?: 'Thank you for your business.',
            'terms'                => $settings->terms ?: 'Payment is due within 14 days.',
            'payment_instructions' => 'Bank transfer to Sample Bank, account 0123456789.',
            // The accounts the business shows customers, as a real one would print them.
            'business_snapshot'    => $this->salesSnapshots->business($workspace, null, $type->isPaidFrom() ? $workspace->base_currency : null),
            'customer_snapshot'    => $this->customer(),
            'template_snapshot'    => $template,
        ]);

        $document->setRelation('lines', $lines);
        $document->setRelation('location', null);
        $document->setRelation('contact', null);
        $document->setRelation('source', $type === DocumentType::CreditNote ? (new SalesDocument)->forceFill(['number' => 'INV-00001']) : null);

        return $document;
    }

    /** A sample receipt for a payment on a sample invoice, drawn with $template. */
    public function receipt(Workspace $workspace, array $template): SalesPayment
    {
        $invoice = $this->sales($workspace, DocumentType::Invoice, []);
        $amount  = Decimal::round(bcdiv((string) $invoice->grand_total, '2', 4));

        $payment = (new SalesPayment)->forceFill([
            'id'                 => 0,
            'kind'               => SalesPayment::PAYMENT,
            'number'             => $this->documentNumbers->preview($workspace, $this->documentNumbers->scheme($workspace, DocumentType::CustomerReceipt), $this->businessCalendar->today($workspace)),
            'status'             => SalesPayment::COMPLETED,
            'payment_date'       => $invoice->document_date,
            'method'             => 'bank_transfer',
            'reference'          => 'TRX-88213',
            'amount'             => $amount,
            'applied_amount'     => $amount,
            'withholding_amount' => '0.00',
            'balance_after'      => bcsub((string) $invoice->grand_total, $amount, 2),
            'template_snapshot'  => $template,
        ]);

        $payment->setRelation('document', $invoice);
        $payment->setRelation('contact', null);
        $payment->setRelation('account', (new PaymentAccount)->forceFill(['name' => 'Main Bank Account', 'currency' => $workspace->base_currency]));

        return $payment;
    }

    /**
     * A sample purchase from a sample supplier, recorded and part paid, drawn
     * with $template: the sample lines, without a combo's parts or warranty.
     */
    public function purchase(Workspace $workspace, array $template): Purchase
    {
        $sale     = $this->sales($workspace, DocumentType::Invoice, []);
        $today    = $this->businessCalendar->today($workspace);
        $paid     = Decimal::round(bcdiv((string) $sale->grand_total, '2', 4));

        $purchase = (new Purchase)->forceFill([
            'id'                      => 0,
            'number'                  => $this->documentNumbers->preview($workspace, $this->documentNumbers->scheme($workspace, DocumentType::Purchase), $today),
            'status'                  => PurchaseStatus::Recorded,
            'payment_status'          => 'partially_paid',
            'document_date'           => $today->toDateString(),
            'due_date'                => $today->addDays(30)->toDateString(),
            'currency'                => $workspace->base_currency,
            'exchange_rate'           => '1',
            'price_tax_mode'          => 'exclusive',
            'supplier_invoice_number' => 'SUP-77120',
            'supplier_invoice_date'   => $today->subDay()->toDateString(),
            'reference'               => 'Office fit-out',
            'subtotal'                => $sale->subtotal,
            'discount_total'          => '0.00',
            'tax_total'               => $sale->tax_total,
            'grand_total'             => $sale->grand_total,
            'base_grand_total'        => $sale->grand_total,
            'taxes'                   => $sale->taxes,
            'amount_paid'             => $paid,
            'withholding_amount'      => '0.00',
            'balance_due'             => bcsub((string) $sale->grand_total, $paid, 2),
            'notes'                   => 'Delivered to the main office.',
            'business_snapshot'       => $this->salesSnapshots->business($workspace, null),
            'supplier_snapshot'       => $this->supplier(),
            'template_snapshot'       => $template,
        ]);

        $purchase->setRelation('lines', $sale->lines->map(fn (SalesDocumentLine $line) => (new PurchaseLine)->forceFill(Arr::except($line->getAttributes(), ['warranty', 'components']))));
        $purchase->setRelation('location', null);
        $purchase->setRelation('contact', null);

        return $purchase;
    }

    /** A sample voucher for a payment on a sample purchase, drawn with $template. */
    public function supplierVoucher(Workspace $workspace, array $template): SupplierPayment
    {
        $purchase = $this->purchase($workspace, []);
        $amount   = (string) $purchase->amount_paid;

        $payment = (new SupplierPayment)->forceFill([
            'id'                 => 0,
            'number'             => $this->documentNumbers->preview($workspace, $this->documentNumbers->scheme($workspace, DocumentType::SupplierVoucher), $this->businessCalendar->today($workspace)),
            'status'             => SupplierPayment::COMPLETED,
            'payment_date'       => $purchase->document_date,
            'method'             => 'bank_transfer',
            'reference'          => 'TRX-40917',
            'amount'             => $amount,
            'applied_amount'     => $amount,
            'withholding_amount' => '0.00',
            'balance_after'      => $purchase->balance_due,
            'template_snapshot'  => $template,
        ]);

        $payment->setRelation('purchase', $purchase);
        $payment->setRelation('contact', null);
        $payment->setRelation('account', (new PaymentAccount)->forceFill(['name' => 'Main Bank Account', 'currency' => $workspace->base_currency]));

        return $payment;
    }

    private function line(array $line, bool $taxed): SalesDocumentLine
    {
        [$name, $description, $quantity, $unit, $price, $discount, $warranty, $components] = $line;

        $amount = bcsub(bcmul($quantity, $price, 2), $discount, 2);

        return (new SalesDocumentLine)->forceFill([
            'name'            => $name,
            'description'     => $description,
            'quantity'        => $quantity,
            'unit'            => $unit,
            'unit_price'      => $price,
            'discount_amount' => $discount,
            'amount'          => $amount,
            'tax_name'        => $taxed ? 'Tax' : null,
            'tax_rate'        => $taxed ? self::TAX_RATE : null,
            'tax_treatment'   => $taxed ? 'standard' : null,
            'tax_amount'      => $taxed ? Decimal::round(bcdiv(bcmul($amount, self::TAX_RATE, 4), '100', 4)) : '0.00',
            'warranty'        => $warranty,
            'components'      => $components,
        ]);
    }

    private function supplier(): array
    {
        return [
            'number'           => null,
            'name'             => 'Sample Supplies Ltd.',
            'trading_name'     => null,
            'contact_person'   => 'Sam Rivera',
            'email'            => 'billing@example.com',
            'phone'            => '+1 555 0142',
            'billing_address'  => ['8 Mill Lane', 'Riverside', '40118'],
            'shipping_address' => null,
            'tax_number'       => null,
        ];
    }

    private function customer(): array
    {
        return [
            'number'           => null,
            'name'             => 'Sample Customer Ltd.',
            'trading_name'     => null,
            'contact_person'   => 'Alex Morgan',
            'email'            => 'accounts@example.com',
            'phone'            => '+1 555 0100',
            'billing_address'  => ['12 Harbour Road', 'Springfield', '40210'],
            'shipping_address' => null,
            'tax_number'       => null,
        ];
    }
}
