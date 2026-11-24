<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Contact;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Api\Sales\SalesLines;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Sales\SalesCalculator;
use App\Services\User\Apps\WorkspaceApps;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * An online order's invoice, issued as the order is confirmed (Doc 19
 * section 19.20 - Phase F2), so the order counts as a sale once from then:
 *
 * - numbered as the business's invoices are, dated the day it is confirmed
 *   and due that day, at the order's location, for its contact (or the one
 *   it was merged into since), in its currency at its rate;
 * - its lines the order's - its products, then its shipping - each at the
 *   price, and with the tax, the order has, totalled as checkout totalled
 *   them (SalesLines, SalesCalculator): a tax changed since is not applied;
 * - the business, customer and template kept as issuing keeps them, the
 *   customer's billing and shipping addresses as the order gives them, the
 *   order's number as the customer's reference, and Settings → Sales's notes
 *   and terms, as a new invoice has them;
 * - made by eCommerce (its source app), so the Sales apps list it.
 */
class EcommerceOrderInvoicing
{
    private const TOTALS = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    private const TAX = ['tax_rate_id', 'tax_name', 'tax_code', 'tax_rate', 'tax_treatment'];

    public function __construct(
        protected SalesDocumentRepository $salesDocuments,
        protected SalesSettingRepository $salesSettings,
        protected LocationRepository $locations,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
        protected SalesSnapshots $snapshots,
        protected DocumentTemplates $templates,
        protected DocumentNumbers $documentNumbers,
        protected ExchangeRateService $exchangeRates,
    ) {}

    /** $order's invoice, issued on $today by $user to $contact - inside the transaction that confirms the order. */
    public function issue(Workspace $workspace, User $user, EcommerceOrder $order, Contact $contact, string $today): SalesDocument
    {
        $rows     = $this->rows($workspace, $order);
        $result   = $this->calculator->calculate(array_map(fn (array $row) => $this->salesLines->forCalculation($row), $rows), null, null, $order->price_tax_mode, $order->tax_rounding, (string) $order->exchange_rate);
        $location = $order->location_id ? $this->locations->findFor($workspace, $order->location_id) : null;
        $settings = $this->salesSettings->forWorkspace($workspace);

        $invoice = $this->salesDocuments->createMadeBy($workspace, [
            'type'               => DocumentType::Invoice,
            'number'             => $this->documentNumbers->next(
                $workspace,
                DocumentType::Invoice,
                CarbonImmutable::parse($today),
                fn (string $number) => $this->salesDocuments->numberTaken($workspace, DocumentType::Invoice, $number),
                $order->location_id,
            ),
            'status'             => SalesDocumentStatus::Issued,
            'payment_status'     => bccomp($result['grand_total'], '0', 2) > 0 ? SalesDocument::PAYMENT_UNPAID : 'paid',
            'contact_id'         => $contact->id,
            'location_id'        => $order->location_id,
            'document_date'      => $today,
            'due_date'           => $today,
            'payment_terms'      => 0,
            'currency'           => $order->currency,
            'exchange_rate'      => (string) $order->exchange_rate,
            ...$this->exchangeRates->originOf($workspace, $order->currency, $today, (string) $order->exchange_rate),
            'price_tax_mode'     => $order->price_tax_mode,
            'tax_rounding'       => $order->tax_rounding,
            'customer_reference' => $order->number,
            'notes'              => $settings->notes,
            'terms'              => $settings->terms,
            ...Arr::only($result, self::TOTALS),
            'balance_due'        => $result['grand_total'],
            'issued_at'          => now(),
            'business_snapshot'  => $this->snapshots->business($workspace, $location, $order->currency),
            'customer_snapshot'  => $this->customer($contact, $order),
            'template_snapshot'  => $this->templates->snapshot($workspace, DocumentType::Invoice),
            'created_by'         => $user->id,
            'updated_by'         => $user->id,
        ], WorkspaceApps::for($workspace)->row('ecommerce')?->app_id);

        $this->salesDocuments->replaceLines($invoice, array_map(fn (array $row, array $calculated) => [...$row, ...$calculated], $rows, $result['lines']));

        return $invoice;
    }

    /**
     * The order's lines as invoice rows, in their order: each product as
     * SalesLines keeps an item's line (its combo's components, its warranty),
     * its variant's options as its description - and the shipping - each with
     * the order's price and tax, never the tax as it is now.
     */
    private function rows(Workspace $workspace, EcommerceOrder $order): array
    {
        $lines = $order->lines->values();
        $rows  = $this->salesLines->rows($workspace, $lines->map(fn (EcommerceOrderLine $line) => [
            'item_id'            => $line->kind === EcommerceOrderLine::PRODUCT ? $line->item_id : null,
            'name'               => $line->name,
            'description'        => $line->options ? implode(', ', array_map(fn (array $option) => trim(($option['name'] ?? '').': '.($option['value'] ?? ''), ': '), $line->options)) : null,
            'quantity'           => (string) $line->quantity,
            'unit'               => $line->unit,
            'unit_price'         => (string) $line->unit_price,
            'price_list_id'      => $line->price_list_id,
            'price_list_rule_id' => $line->price_list_rule_id,
        ])->all());

        return array_map(fn (array $row, EcommerceOrderLine $line) => [
            ...$row,
            ...$line->only(self::TAX),
            'tax_rate' => $line->tax_rate === null ? null : (string) $line->tax_rate,
        ], $rows, $lines->all());
    }

    /** The customer as issuing keeps them, with the addresses the order gives: its shipping one only when it is shipped. */
    private function customer(Contact $contact, EcommerceOrder $order): array
    {
        $address = fn (array $given) => $this->snapshots->addressLines([...Arr::except($given, 'region'), 'state' => $given['region'] ?? null]);

        return [
            ...$this->snapshots->customer($contact),
            'billing_address'  => $address($order->billing_address ?? []),
            'shipping_address' => $order->shipping_address ? $address($order->shipping_address) : null,
        ];
    }
}
