<?php

namespace App\Models\Billing;

use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment for izSuite's invoice taken by a provider (Doc 15 section 14 -
 * Doc 13 Phase D2): Stripe's payment, its amount and the card it came from.
 * One per provider reference, however often the provider reports it. A
 * payment that could not pay its invoice - cancelled before it arrived, or
 * not its total - says why in `note`, for the admin to refund. Never deleted.
 */
class SubscriptionPayment extends Model
{
    public const SUCCEEDED       = 'succeeded';

    /** A saved card charged without the business there (Phase D3), refused by the bank: `note` says why. */
    public const FAILED          = 'failed';

    /** The bank asked the cardholder to confirm it (3-D Secure): paid on Stripe's page instead. */
    public const ACTION_REQUIRED = 'action_required';

    /** Not settled yet: Stripe's notification settles it. */
    public const PROCESSING      = 'processing';

    protected $fillable = [
        'workspace_id',
        'subscription_invoice_id',
        'provider',
        'reference',
        'currency',
        'amount',
        'status',
        'attempted_on',
        'card',
        'note',
    ];

    protected $casts    = [
        'amount'       => 'decimal:2',
        'attempted_on' => 'date',
    ];

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
