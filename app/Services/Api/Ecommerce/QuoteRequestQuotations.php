<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Contact;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Models\User\Item;
use App\Models\User\SalesDocument;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\Sales\SalesDocumentService;
use App\Services\Api\Sales\SalesFormService;
use App\Services\Api\Sales\SalesLines;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Countries;
use App\Support\Decimal;

/**
 * A request for a quotation made a draft quotation (Doc 19 section 19.30 -
 * Phase H4c), while Settings → eCommerce says so and Sales is on: one for
 * the business to price and send.
 *
 * - Its customer is the contact where it is known - the signed-in account's,
 *   or the one an earlier online order made for that e-mail, as checkout
 *   finds it - else none yet, for a member to choose or add: a request makes
 *   no contact by itself.
 * - It starts as a new quotation does (SalesFormService::defaults()): dated
 *   the business's today, valid for Settings → Sales's quotation days, with
 *   its notes and terms. It is at the storefront's location, in the base
 *   currency, the request's number its customer's reference and the request
 *   itself in its internal notes.
 * - Each product is a line - its item while it is on, its title, how many
 *   and its unit - at the item's sale price, with the tax a new line takes:
 *   none while taxes are off, the customer's own treatment's, else the
 *   item's while usable, else the business's default.
 * - It is a draft, numbered when issued, made in eCommerce, and in the
 *   Activity Log, put down to the customer.
 */
class QuoteRequestQuotations
{
    public function __construct(
        protected SalesFormService $forms,
        protected SalesDocumentService $documents,
        protected SalesDocumentRepository $salesDocuments,
        protected SalesLines $salesLines,
        protected ContactRepository $contacts,
        protected EcommerceOrderRepository $orders,
        protected ItemRepository $items,
        protected TaxRateRepository $taxRates,
    ) {}

    /** $request's draft quotation, $account the customer signed in, if they were - or none while Sales is off. Made inside the request's own transaction. */
    public function make(Storefront $storefront, EcommerceQuoteRequest $request, ?StorefrontCustomer $account): ?SalesDocument
    {
        $workspace = $storefront->workspace;

        if (! WorkspaceApps::for($workspace)->has('sales')) {
            return null;
        }

        $contact = $this->contactOf($workspace, $request, $account);
        $start   = $this->forms->defaults($workspace, DocumentType::Quotation, ['currency' => $workspace->base_currency, ...($contact ? ['contact_id' => $contact->id] : [])]);
        $header  = [
            'type'               => DocumentType::Quotation,
            'status'             => SalesDocumentStatus::Draft,
            'contact_id'         => $contact?->id,
            'location_id'        => $storefront->location_id,
            'document_date'      => $start['document_date'],
            'expiry_date'        => $start['expiry_date'],
            'currency'           => $workspace->base_currency,
            'exchange_rate'      => '1',
            'price_tax_mode'     => $start['price_tax_mode'],
            'tax_rounding'       => $start['tax_rounding'],
            'customer_reference' => $request->number,
            'notes'              => $start['notes'],
            'internal_notes'     => $this->internalNotes($storefront, $request),
            'terms'              => $start['terms'],
            // The full Sales app's shipping terms, as a new quotation starts with them.
            ...($start['shipping_terms'] ?? null ? ['shipping_terms' => $start['shipping_terms']] : []),
        ];

        [$totals, $rows] = $this->documents->totals($header, $this->rows($workspace, $request, $start));

        $quotation = $this->salesDocuments->createMadeBy($workspace, [...$header, ...$totals], WorkspaceApps::for($workspace)->row('ecommerce')?->app_id);
        $this->salesDocuments->replaceLines($quotation, array_map(fn (array $row) => [...$row, 'source_line_id' => null], $rows));

        return $quotation;
    }

    /** Once $quotation is saved with its $request: in the Activity Log, put down to the customer. */
    public function made(Storefront $storefront, EcommerceQuoteRequest $request, SalesDocument $quotation): void
    {
        ActivityLogService::recordCustomer($storefront->workspace, 'created', "Added a draft quotation for {$request->customer_name} from the request for a quotation {$request->number} on {$storefront->name}", $quotation);
    }

    /** The signed-in account's contact, or the one an earlier online order made for the e-mail - while it is an active customer - or none. */
    private function contactOf(Workspace $workspace, EcommerceQuoteRequest $request, ?StorefrontCustomer $account): ?Contact
    {
        $contact = $account?->contact_id
            ? $this->contacts->findFor($workspace, $account->contact_id)
            : $this->orders->contactMadeFor($workspace, $request->customer_email);

        return $contact && $contact->is_active && $contact->isCustomer() ? $contact : null;
    }

    /** Each product as a new quotation's line takes an item (the Sales form's addItem()). */
    private function rows(Workspace $workspace, EcommerceQuoteRequest $request, array $start): array
    {
        $items  = $this->items->findManyWithComponents($workspace->id, $request->items->pluck('item_id')->filter()->values()->all())->keyBy('id');
        $usable = $this->taxRates->getFor($workspace)
            ->filter(fn (TaxRate $rate) => $rate->is_active && $rate->for_sales && $rate->effective_from <= $start['document_date'] && (! $rate->effective_to || $rate->effective_to >= $start['document_date']))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $this->salesLines->rows($workspace, $request->items->map(function (EcommerceQuoteRequestItem $line) use ($items, $start, $usable) {
            $item  = $items->get($line->item_id ?? 0);
            $item  = $item?->is_active ? $item : null;
            $price = $item ? ($item->isCombo() && $item->combo_pricing === 'sum' ? $item->componentTotal() : $item->sale_price) : null;

            return [
                'item_id'     => $item?->id,
                'name'        => $line->title,
                'description' => $item?->sales_description,
                'quantity'    => (string) $line->quantity,
                'unit'        => $item?->unit,
                'unit_price'  => Decimal::round((string) ($price ?? '0')),
                'tax_rate_id' => $this->tax($item, $start, $usable),
            ];
        })->all());
    }

    /** A line's tax, as the Sales form gives a new one (lineTax()). */
    private function tax(?Item $item, array $start, array $usable): ?int
    {
        if (! $start['taxes_enabled']) {
            return null;
        }

        if ($start['customer_treatment']) {
            return $start['customer_tax_id'];
        }

        return $item?->sales_tax_id !== null && in_array((int) $item->sales_tax_id, $usable, true) ? (int) $item->sales_tax_id : $start['default_tax_id'];
    }

    /** The request, for whoever prices it: who asked, where and when it goes, and their message. */
    private function internalNotes(Storefront $storefront, EcommerceQuoteRequest $request): string
    {
        return implode("\n", array_filter([
            "From the request for a quotation {$request->number} on {$storefront->name}:",
            implode(', ', array_filter([$request->customer_name, $request->company])),
            implode(', ', array_filter([$request->customer_email, $request->customer_phone])),
            'Delivered to: '.implode(', ', array_filter([$request->delivery_address, $request->delivery_city, Countries::name($request->delivery_country)])),
            $request->required_on ? 'Wanted by: '.$request->required_on->toDateString() : null,
            $request->message ? "Message: {$request->message}" : null,
        ]));
    }
}
