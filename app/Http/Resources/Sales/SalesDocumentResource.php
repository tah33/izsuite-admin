<?php

namespace App\Http\Resources\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDocument;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Documents\DocumentLinks;
use App\Services\Sales\SalesDocumentRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
 * loaded.
 */
class SalesDocumentResource extends JsonResource
{
    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(SalesDocumentRules::class);

        return [
            'id'                   => $this->id,
            'type'                 => $this->type->value,
            'number'               => $this->number,
            'revision'             => $this->revision,
            'status'               => $rules->status($this->resource, $this->today),
            'payment_status'       => $this->payment_status,
            'overdue'              => $rules->overdue($this->resource, $this->today),
            'actions'              => $rules->actions($this->resource, $this->today),
            'contact_id'           => $this->contact_id,
            'customer'             => $this->customer_snapshot ?? ($this->contact ? app(SalesSnapshots::class)->customer($this->contact) : null),
            'business'             => $this->business_snapshot,
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
            'discount_type'        => $this->discount_type,
            'discount_value'       => $this->discount_value,
            'lines'                => SalesDocumentLineResource::collection($this->lines),
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
            'balance_due'          => $this->balance_due,
            'refundable'           => $this->type === DocumentType::CreditNote ? $rules->refundable($this->resource) : null,
            'notes'                => $this->notes,
            'terms'                => $this->terms,
            'payment_instructions' => $this->payment_instructions,
            'status_reason'        => $this->status_reason,
            'reason'               => $this->reason,
            'source'               => $this->source ? $this->link($this->source) : null,
            'conversions'          => $this->conversions->map(fn (SalesDocument $document) => $this->link($document))->all(),
            'credit_notes'         => $this->creditNotes->map(fn (SalesDocument $note) => [
                ...$this->link($note),
                'document_date'  => $note->document_date,
                'grand_total'    => $note->grand_total,
                'balance_due'    => $note->balance_due,
                'payment_status' => $note->payment_status,
            ])->all(),
            'payments'             => SalesPaymentListResource::collection($this->payments),
            'issued_at'            => $this->issued_at?->toIso8601String(),
            'delivery'             => $rules->delivery($this->resource, $this->today),
            'link'                 => $this->activeLink ? new DocumentLinkResource(['link' => $this->activeLink, 'url' => app(DocumentLinks::class)->url($this->activeLink)]) : null,
            'emails'               => DocumentEmailResource::collection($this->emails),
        ];
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
