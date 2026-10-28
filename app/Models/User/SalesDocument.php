<?php

namespace App\Models\User;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quotation, proforma invoice, invoice or credit note of one business (Doc
 * 08). Its totals are worked out by SalesCalculator from its lines - never
 * taken from the form - in its own currency and, at its exchange rate, in the
 * business's base currency.
 *
 * A credit note's source is the invoice it credits. What an invoice has been
 * paid, withheld, credited and refunded - and what a credit note has applied
 * and refunded - is kept up to date by InvoiceSettlement.
 *
 * A draft has no number. Issuing an invoice, or first sending a quotation or
 * proforma, numbers it and keeps the business and the customer as they were
 * then (business_snapshot, customer_snapshot); later changes to either never
 * reach it. An issued invoice's amounts are locked.
 *
 * document_date, due_date and expiry_date are left as the "2026-10-28"
 * strings the columns hold: a date cast would store them with a time on
 * SQLite, and comparing them with today would go wrong there. Its attachments
 * are files in the "attachments" collection.
 */
class SalesDocument extends Model
{
    use HasFiles;

    public const PAYMENT_NOT_APPLICABLE = 'not_applicable';

    public const PAYMENT_UNPAID = 'unpaid';

    /** Everything the form, a status change or a settlement sets; totals are SalesCalculator's. */
    protected $fillable = [
        'type',
        'number',
        'revision',
        'status',
        'payment_status',
        'contact_id',
        'location_id',
        'document_date',
        'due_date',
        'expiry_date',
        'payment_terms',
        'currency',
        'exchange_rate',
        'price_tax_mode',
        'tax_rounding',
        'customer_reference',
        'sales_reference',
        'discount_type',
        'discount_value',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'net_total',
        'base_grand_total',
        'base_tax_total',
        'base_net_total',
        'taxes',
        'amount_paid',
        'withholding_amount',
        'credited_amount',
        'applied_amount',
        'refunded_amount',
        'balance_due',
        'notes',
        'terms',
        'payment_instructions',
        'business_snapshot',
        'customer_snapshot',
        'source_id',
        'status_reason',
        'reason',
        'issued_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'revision'       => 0,
        'status'         => 'draft',
        'payment_status' => self::PAYMENT_NOT_APPLICABLE,
    ];

    protected $casts = [
        'type'              => DocumentType::class,
        'status'            => SalesDocumentStatus::class,
        'revision'          => 'integer',
        'contact_id'        => 'integer',
        'location_id'       => 'integer',
        'payment_terms'     => 'integer',
        'exchange_rate'     => 'decimal:8',
        'discount_value'    => 'decimal:4',
        'subtotal'          => 'decimal:2',
        'discount_total'    => 'decimal:2',
        'tax_total'         => 'decimal:2',
        'grand_total'       => 'decimal:2',
        'net_total'         => 'decimal:2',
        'base_grand_total'  => 'decimal:2',
        'base_tax_total'    => 'decimal:2',
        'base_net_total'    => 'decimal:2',
        'taxes'             => 'array',
        'amount_paid'        => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'credited_amount'    => 'decimal:2',
        'applied_amount'     => 'decimal:2',
        'refunded_amount'    => 'decimal:2',
        'balance_due'        => 'decimal:2',
        'business_snapshot' => 'array',
        'customer_snapshot' => 'array',
        'source_id'         => 'integer',
        'issued_at'         => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === SalesDocumentStatus::Draft;
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The customer. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Its lines, in their order. */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesDocumentLine::class)->orderBy('position')->orderBy('id');
    }

    /** Its earlier versions, oldest first. */
    public function revisions(): HasMany
    {
        return $this->hasMany(SalesDocumentRevision::class)->orderBy('revision');
    }

    /** The quotation or proforma it was converted from - or, for a credit note, the invoice it credits. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    /** The documents converted from it. */
    public function conversions(): HasMany
    {
        return $this->hasMany(self::class, 'source_id')->where('type', '!=', DocumentType::CreditNote->value)->orderBy('id');
    }

    /** An invoice's credit notes, in the order they were made. */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'source_id')->where('type', DocumentType::CreditNote->value)->orderBy('id');
    }

    /** An invoice's payments, or a credit note's refunds - recorded and reversed, oldest first. */
    public function payments(): HasMany
    {
        return $this->hasMany(SalesPayment::class)->orderBy('payment_date')->orderBy('id');
    }
}
