<?php

namespace App\Models\User;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expense of one business (Doc 10): what it spent on running itself - rent,
 * utilities, transport - by category. Its payee is one of its contacts, or a
 * name typed in for this one expense (payee_name), which never becomes a
 * contact (section 5). Its totals are SalesCalculator's, worked out from its
 * lines - never taken from the form - in its own currency and, at its
 * exchange rate, in the business's base currency.
 *
 * A draft has no number. Recording it numbers it and keeps the business and
 * the payee as they were then (business_snapshot, payee_snapshot); later
 * changes to either never reach it. A recorded expense's amounts are locked,
 * and what it still owes is balance_due.
 *
 * document_date (the expense date) and due_date are left as the "2026-10-28"
 * strings the columns hold, as a purchase's are. Its attachments - the
 * receipt among them - are files in the "attachments" collection.
 */
class Expense extends Model
{
    use HasFiles;

    public const PAYMENT_NOT_APPLICABLE = 'not_applicable';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIALLY_PAID = 'partially_paid';

    public const PAYMENT_PAID = 'paid';

    /** Everything the form or a status change sets; totals are SalesCalculator's. */
    protected $fillable = [
        'number',
        'status',
        'payment_status',
        'contact_id',
        'payee_name',
        'location_id',
        'document_date',
        'due_date',
        'payment_terms',
        'currency',
        'exchange_rate',
        'price_tax_mode',
        'tax_rounding',
        'receipt_number',
        'reference',
        'subtotal',
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
        'payee_snapshot',
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
        'status'             => ExpenseStatus::class,
        'contact_id'         => 'integer',
        'location_id'        => 'integer',
        'payment_terms'      => 'integer',
        'exchange_rate'      => 'decimal:8',
        'subtotal'           => 'decimal:2',
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
        'payee_snapshot'     => 'array',
        'template_snapshot'  => 'array',
        'recorded_at'        => 'datetime',
    ];

    public function isDraft(): bool
    {
        return $this->status === ExpenseStatus::Draft;
    }

    /** Who was paid, as the expense names them: as it kept them, else the contact as they are, else the name typed in. */
    public function payeeName(): ?string
    {
        return $this->payee_snapshot['name'] ?? $this->contact?->name ?? $this->payee_name;
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The payee, when one of the business's contacts. */
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
        return $this->hasMany(ExpenseLine::class)->orderBy('position')->orderBy('id');
    }

    /** What has been paid to its payee, oldest first - reversed payments too. */
    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->orderBy('payment_date')->orderBy('id');
    }
}
