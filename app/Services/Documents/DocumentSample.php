<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;

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
            'business_snapshot'    => $this->salesSnapshots->business($workspace, null),
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
