<?php

namespace App\Models\Billing;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money izSuite gave back for a subscription payment (Doc 15 section 14 -
 * Phase C2a), with the admin's reason. A card's goes through its provider
 * and counts once the provider confirms it; a bank transfer's is recorded
 * by hand once izSuite has sent it. It changes neither the invoice nor the
 * plan, and is never deleted.
 */
class SubscriptionRefund extends Model
{
    /** Asked of the provider, not confirmed yet. */
    public const PENDING   = 'pending';

    /** Confirmed by the provider - or, for a transfer, recorded by izSuite. */
    public const SUCCEEDED = 'succeeded';

    /** The provider did not refund it: `note` says why. */
    public const FAILED    = 'failed';

    protected $fillable = [
        'workspace_id',
        'subscription_payment_id',
        'subscription_invoice_id',
        'provider',
        'reference',
        'transfer_reference',
        'currency',
        'amount',
        'status',
        'reason',
        'note',
        'refunded_by',
        'confirmed_at',
    ];

    protected $casts    = [
        'amount'       => 'decimal:2',
        'confirmed_at' => 'datetime',
    ];

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    public function pending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    /** The admin who refunded it; null for one made in the provider's own dashboard. */
    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
