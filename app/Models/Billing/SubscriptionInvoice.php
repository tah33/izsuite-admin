<?php

namespace App\Models\Billing;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * izSuite's invoice to a business for its subscription (Doc 13 sections 11
 * and 15) - and, once izSuite confirms the bank transfer, its receipt. It
 * keeps the plan, the price, who it was billed to, izSuite's details and the
 * transfer instructions as they were when issued, so its PDF never changes.
 * It is never one of the business's own documents.
 */
class SubscriptionInvoice extends Model
{
    public const OPEN   = 'open';

    public const PAID   = 'paid';

    public const VOID   = 'void';

    protected $fillable = [
        'workspace_id',
        'number',
        'package_id',
        'package_name',
        'cycle',
        'period_starts_on',
        'period_ends_on',
        'currency',
        'amount',
        'credit',
        'total',
        'status',
        'issued_on',
        'due_on',
        'billed_to',
        'seller',
        'payment_instructions',
        'transfer_reference',
        'receipt_number',
        'paid_at',
        'confirmed_by',
        'voided_at',
    ];

    protected $casts    = [
        'period_starts_on' => 'date',
        'period_ends_on'   => 'date',
        'amount'           => 'decimal:2',
        'credit'           => 'decimal:2',
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

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
