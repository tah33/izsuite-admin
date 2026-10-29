<?php

namespace App\Models\User;

use App\Enums\PurchaseStatus;
use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase of one business (Doc 09): goods or services bought from a
 * supplier, on their invoice, their receipt or for cash. Its totals are
 * SalesCalculator's, worked out from its lines - never taken from the form -
 * in its own currency and, at its exchange rate, in the business's base
 * currency.
 *
 * A draft has no number. Recording it numbers it and keeps the business and
 * the supplier as they were then (business_snapshot, supplier_snapshot);
 * later changes to either never reach it. A recorded purchase's amounts are
 * locked, and what it still owes is balance_due.
 *
 * document_date (the purchase date), due_date and supplier_invoice_date are
 * left as the "2026-10-28" strings the columns hold, as a sales document's
 * are. Its attachments - the supplier's invoice or receipt among them - are
 * files in the "attachments" collection.
 */
class Purchase extends Model
{
    use HasFiles;

    public const PAYMENT_NOT_APPLICABLE = 'not_applicable';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    /** Everything the form or a status change sets; totals are SalesCalculator's. */
    protected $fillable = [
        'number',
        'status',
        'payment_status',
        'contact_id',
        'location_id',
        'document_date',
        'due_date',
        'payment_terms',
        'currency',
        'exchange_rate',
        'price_tax_mode',
        'tax_rounding',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'reference',
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
        'balance_due',
        'notes',
        'business_snapshot',
        'supplier_snapshot',
        'template_snapshot',
        'status_reason',
        'duplicate_reason',
        'recorded_at',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'status'         => 'draft',
        'payment_status' => self::PAYMENT_NOT_APPLICABLE,
    ];

    protected $casts = [
        'status'             => PurchaseStatus::class,
        'contact_id'         => 'integer',
        'location_id'        => 'integer',
        'payment_terms'      => 'integer',
        'exchange_rate'      => 'decimal:8',
        'discount_value'     => 'decimal:4',
        'subtotal'           => 'decimal:2',
        'discount_total'     => 'decimal:2',
        'tax_total'          => 'decimal:2',
        'grand_total'        => 'decimal:2',
        'net_total'          => 'decimal:2',
        'base_grand_total'   => 'decimal:2',
        'base_tax_total'     => 'decimal:2',
        'base_net_total'     => 'decimal:2',
        'taxes'              => 'array',
        'amount_paid'        => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'balance_due'        => 'decimal:2',
        'business_snapshot'  => 'array',
        'supplier_snapshot'  => 'array',
        'template_snapshot'  => 'array',
        'recorded_at'        => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::Draft;
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The supplier. */
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
        return $this->hasMany(PurchaseLine::class)->orderBy('position')->orderBy('id');
    }
}
