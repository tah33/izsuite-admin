<?php

namespace App\Models\Billing;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * izSuite's invoice to a business for its subscription (Doc 13 sections 11
 * and 15) - and, once izSuite confirms the bank transfer, its receipt. It
 * keeps the plan, the price, who it was billed to, izSuite's details and the
 * transfer instructions as they were when issued, so its PDF never changes.
 * It is never one of the business's own documents.
 */
class SubscriptionInvoice extends Model
{
    public const OPEN    = 'open';

    public const PAID    = 'paid';

    public const VOID    = 'void';

    /** What it is for: a subscription's first payment, a renewal, or (Phase C2b) an upgrade. */
    public const NEW     = 'new';

    public const RENEWAL = 'renewal';

    public const UPGRADE = 'upgrade';

    protected $fillable  = [
        'workspace_id',
        'number',
        'kind',
        'package_id',
        'package_name',
        'cycle',
        'period_starts_on',
        'period_ends_on',
        'currency',
        'amount',
        'credit',
        // Its tax (Phase D1), as worked out when issued: the rule's name and rate, the amount, a reverse charge's note.
        'tax_name',
        'tax_rate',
        'tax',
        'tax_note',
        'total',
        'status',
        'issued_on',
        'due_on',
        'billed_to',
        'seller',
        'payment_instructions',
        'transfer_reference',
        // Paid by card (Phase D2): "Visa ending 4242", and the provider's reference.
        'paid_with',
        'payment_reference',
        'receipt_number',
        'paid_at',
        'confirmed_by',
        'voided_at',
    ];

    protected $casts     = [
        'period_starts_on' => 'date',
        'period_ends_on'   => 'date',
        'amount'           => 'decimal:2',
        'credit'           => 'decimal:2',
        'tax_rate'         => 'decimal:3',
        'tax'              => 'decimal:2',
        'total'            => 'decimal:2',
        'issued_on'        => 'date',
        'due_on'           => 'date',
        'billed_to'        => 'array',
        'seller'           => 'array',
        'paid_at'          => 'datetime',
        'voided_at'        => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** The admin who confirmed the payment. */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Its payments - a card's, the saved card's attempts (Phases D2 and D3), a transfer izSuite confirmed (Doc 15 Phase C2a) - oldest first. */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_invoice_id')->orderBy('id');
    }

    /** Its payments' refunds (Doc 15 Phase C2a), oldest first. */
    public function refunds(): HasMany
    {
        return $this->hasMany(SubscriptionRefund::class, 'subscription_invoice_id')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** What it charges before tax: the price less any upgrade credit - what the tax is worked out on. */
    public function net(): string
    {
        return bcsub((string) $this->amount, (string) $this->credit, 2);
    }

    /** Its tax rate as people read it, "15" or "7.5" - null without a tax. */
    public function taxPercent(): ?string
    {
        return $this->tax_name === null ? null : SubscriptionTax::readable((string) $this->tax_rate);
    }
}
