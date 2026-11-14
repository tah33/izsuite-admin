<?php

namespace App\Http\Resources\Sales;

use App\Services\Shared\EditConflicts;
use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Http\Resources\Concerns\HidesValues;
use App\Http\Resources\Contacts\ContactResource;
use App\Repositories\User\CustomerAdvanceRepository;
use App\Repositories\User\SalesAllocationRepository;
use App\Repositories\User\SalesDeliveryRepository;
use App\Repositories\User\SalesReturnRepository;
use App\Models\User\SalesReturn;
use App\Models\User\CustomerAdvance;
use App\Models\User\SalesDelivery;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Services\Api\Sales\CustomerAdvanceService;
use App\Services\Api\Sales\SalesOrderDeposits;
use App\Services\Api\Sales\SalesDeliveryService;
use App\Services\Api\Sales\SalesOrderInvoicing;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Documents\DocumentLinks;
use App\Services\Shared\AllowedActions;
use App\Services\Sales\SalesDocumentRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * A sales document whole - for its page and its edit form: the header, the
 * lines, the totals and tax breakdown, where it came from and what was made
 * from it, and the actions its status allows now (see SalesDocumentRules).
 * An invoice also carries its payments and credit notes; a credit note its
 * refunds, with what it may still refund. How it may reach the customer -
 * email, reminder, share - comes as `delivery`, with its working secure link
 * and the emails it went out in.
 *
 * The customer and business are as they were when it was numbered; a draft
 * shows the customer as they are. Expects lines, contact, location, source,
 * conversions, creditNotes, payments.account, activeLink and emails.sender
 * loaded. Its amounts are empty for a user without Sales Values, and the
 * customer's and business's tax details for one without View Tax
 * Information.
 */
class SalesDocumentResource extends JsonResource
{
    use HidesValues;

    /** What a user without Sales Values does not see (Doc 12 section 14). */
    private const VALUES = [
        'discount_value', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'taxes' => [],
        'amount_paid', 'withholding_amount', 'credited_amount', 'applied_amount', 'refunded_amount', 'allocated_amount', 'balance_due', 'refundable',
    ];

    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(SalesDocumentRules::class);

        return $this->hideValues('sales', [
            'id'                   => $this->id,
            // When it was last changed: sent back as If-Match when it is saved (Doc 14 section 22).
            'version' => EditConflicts::version($this->resource),
            'type'                 => $this->type->value,
            'number'               => $this->number,
            'revision'             => $this->revision,
            'status'               => $rules->status($this->resource, $this->today),
            'payment_status'       => $this->payment_status,
            // A sales order's, beside its status (Doc 16 Phase B).
            'delivery_status'      => $this->delivery_status,
            'invoice_status'       => $this->invoice_status,
            'confirmed_at'         => $this->confirmed_at?->toIso8601String(),
            'overdue'              => $rules->overdue($this->resource, $this->today),
            'actions'              => app(AllowedActions::class)->sales($this->type, $rules->actions($this->resource, $this->today)),
            'contact_id'           => $this->contact_id,
            'customer'             => $this->party($this->customer_snapshot ?? ($this->contact ? app(SalesSnapshots::class)->customerOf($this->resource) : null)),
            // What the page's sheet shows of the business as it was at issue - its tax numbers with Tax Information.
            'business'             => $this->business_snapshot ? $this->hideWithout('taxes.view', Arr::only($this->business_snapshot, ['name', 'address', 'phone', 'tax_numbers']), ['tax_numbers' => []]) : null,
            'location_id'          => $this->location_id,
            'location'             => $this->location?->name,
            'document_date'        => $this->document_date,
            'due_date'             => $this->due_date,
            'expiry_date'          => $this->expiry_date,
            'payment_terms'        => $this->payment_terms,
            'currency'             => $this->currency,
            'exchange_rate'        => $this->exchange_rate,
            'price_tax_mode'       => $this->price_tax_mode,
            'tax_rounding'         => $this->tax_rounding,
            'customer_reference'   => $this->customer_reference,
            'sales_reference'      => $this->sales_reference,
            // The full Sales app's (Doc 16 Phase A).
            'salesperson'            => $this->salesperson_id ? ['id' => $this->salesperson_id, 'name' => $this->salesperson?->name] : null,
            'sales_team'             => $this->sales_team_id ? ['id' => $this->sales_team_id, 'name' => $this->salesTeam?->name] : null,
            'billing_address'        => $this->billing_address,
            'shipping_address'       => $this->shipping_address,
            'expected_delivery_date' => $this->expected_delivery_date,
            'shipping_method'        => $this->shipping_method,
            'shipping_terms'         => $this->shipping_terms,
            'discount_type'        => $this->discount_type,
            'discount_value'       => $this->discount_value,
            'progress'             => $this->type === DocumentType::SalesOrder ? $this->progress($request) : null,
            // A sales order's delivery notes (Phase C).
            'deliveries'           => $this->type === DocumentType::SalesOrder
                ? app(SalesDeliveryRepository::class)->forOrder($this->resource)->map(fn (SalesDelivery $delivery) => $delivery->only(['id', 'number', 'status', 'delivery_date', 'delivered_on']))->all()
                : null,
            // Its customer returns (Phase E).
            'returns'              => $this->type === DocumentType::SalesOrder
                ? app(SalesReturnRepository::class)->forOrder($this->resource)->map(fn (SalesReturn $return) => $return->only(['id', 'number', 'status', 'return_date']))->all()
                : null,
            'lines'                => SalesDocumentLineResource::collection($this->lines),
            // An invoice made from a sales order, billing beyond what it had left: why (Doc 16 Phase B2).
            'over_invoice_reason'  => $this->when($this->type === DocumentType::Invoice, fn () => $this->over_invoice_reason),
            // A sales order's deposit invoice (Doc 16 Phase D2), and an order's deposits with what is deducted of each.
            'is_deposit'           => (bool) $this->is_deposit,
            // Why its discount went past the role's limit (Doc 16 Phase F2).
            'discount_reason'      => $this->discount_reason,
            'deposits'             => $this->type === DocumentType::SalesOrder
                ? array_map(fn (array $deposit) => $this->hideValues('sales', $deposit, ['grand_total', 'deducted']), app(SalesOrderDeposits::class)->forOrder($this->resource))
                : null,
            'subtotal'             => $this->subtotal,
            'discount_total'       => $this->discount_total,
            'tax_total'            => $this->tax_total,
            'grand_total'          => $this->grand_total,
            'net_total'            => $this->net_total,
            'base_grand_total'     => $this->base_grand_total,
            'taxes'                => $this->taxes ?? [],
            'amount_paid'          => $this->amount_paid,
            'withholding_amount'   => $this->withholding_amount,
            'credited_amount'      => $this->credited_amount,
            'applied_amount'       => $this->applied_amount,
            'refunded_amount'      => $this->refunded_amount,
            // An invoice's credit from advances and other credit notes; a credit note's applied to other invoices (Doc 16 Phase D1).
            'allocated_amount'     => $this->allocated_amount,
            'balance_due'          => $this->balance_due,
            'refundable'           => $this->type === DocumentType::CreditNote ? $rules->refundable($this->resource) : null,
            'notes'                => $this->notes,
            'internal_notes'       => $this->internal_notes,
            'terms'                => $this->terms,
            'payment_instructions' => $this->payment_instructions,
            'status_reason'        => $this->status_reason,
            'reason'               => $this->reason,
            'source'               => $this->source ? $this->link($this->source) : null,
            'conversions'          => $this->conversions->map(fn (SalesDocument $document) => $this->link($document))->all(),
            'credit_notes'         => $this->creditNotes->map(fn (SalesDocument $note) => $this->hideValues('sales', [
                ...$this->link($note),
                'document_date'  => $note->document_date,
                'grand_total'    => $note->grand_total,
                'balance_due'    => $note->balance_due,
                'payment_status' => $note->payment_status,
            ], ['grand_total', 'balance_due']))->all(),
            'payments'             => SalesPaymentListResource::collection($this->payments),
            // Credit applied to an invoice, or out of a credit note, the removed ones too (Doc 16 Phase D1).
            'allocations'          => in_array($this->type, [DocumentType::Invoice, DocumentType::CreditNote], true)
                ? SalesAllocationResource::collection(app(SalesAllocationRepository::class)->historyOf($this->resource))
                : null,
            // A sales order's advances, or those kept from payments over an invoice.
            'advances'             => in_array($this->type, [DocumentType::SalesOrder, DocumentType::Invoice], true)
                ? $this->hideValues('sales', ['rows' => app(CustomerAdvanceRepository::class)->forDocument($this->resource)->map(fn (CustomerAdvance $advance) => $advance->only(['id', 'number', 'status', 'payment_date', 'currency', 'amount', 'balance']))->all()], ['rows' => []])['rows']
                : null,
            // Whether a payment over the balance may be kept as the customer's advance, once confirmed (section 16.16).
            'keeps_overpayments'   => $this->type === DocumentType::Invoice && app(CustomerAdvanceService::class)->keepsOverpayments($this->resource->workspace),
            'issued_at'            => $this->issued_at?->toIso8601String(),
            'delivery'             => app(AllowedActions::class)->delivery($this->type, $rules->delivery($this->resource, $this->today)),
            'link'                 => $this->activeLink ? new DocumentLinkResource(['link' => $this->activeLink, 'url' => app(DocumentLinks::class)->url($this->activeLink)]) : null,
            'emails'               => DocumentEmailResource::collection($this->emails),

            // The app that made it, and who (Doc 14 section 14.5), where it was read with them
            'made_in' => $this->whenLoaded('sourceApp', fn () => $this->sourceApp?->name),
            'made_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),

            // Where its exchange rate came from (Doc 14 section 14.9): the business's own rates of that date, or entered on it
            'exchange_rate_date'   => $this->exchange_rate_date,
            'exchange_rate_source' => $this->exchange_rate_source,
        ], self::VALUES);
    }

    /**
     * A sales order's lines: what was delivered, invoiced, returned and
     * credited of each (Doc 16 section 16.28) - and, while it is confirmed,
     * what draft invoices hold of it and what is left to invoice (Phase B2).
     */
    private function progress(Request $request): array
    {
        $confirmed  = $this->status === SalesDocumentStatus::Confirmed;
        $invoicing  = $confirmed ? app(SalesOrderInvoicing::class)->lines($request->attributes->get('workspace'), $this->resource) : [];
        // What is left to deliver of each, besides what open deliveries hold (Phase C).
        $delivering = $confirmed ? app(SalesDeliveryService::class)->left($this->resource) : [];

        return $this->lines->map(fn (SalesDocumentLine $line) => [
            'line_id'   => $line->id,
            'delivered' => $line->delivered_quantity,
            'invoiced'  => $line->invoiced_quantity,
            'returned'  => $line->returned_quantity,
            'credited'  => $line->credited_quantity,
            'drafted'   => $invoicing[$line->id]['drafted'] ?? null,
            'left'      => $invoicing[$line->id]['left'] ?? null,
            'to_deliver' => $delivering[$line->id]['left'] ?? null,
            'on_deliveries' => $delivering[$line->id]['held'] ?? null,
        ])->all();
    }

    /** The customer as kept - their tax details hidden from a user without View Tax Information. */
    private function party(?array $customer): ?array
    {
        return $customer ? $this->hideWithout('taxes.view', $customer, ContactResource::TAX_DETAILS) : null;
    }

    /** Another document it is linked with, as the page links to it. */
    private function link(SalesDocument $document): array
    {
        return [
            'id'     => $document->id,
            'kind'   => $document->type->kind(),
            'label'  => $document->type->label(),
            'number' => $document->number,
            'status' => $document->status->value,
        ];
    }
}
