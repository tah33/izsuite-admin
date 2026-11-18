<?php

namespace App\Models\Billing;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of izSuite's credit to a business (Doc 15 Phase C2b): given
 * by an admin with a reason, taken off an invoice, given back when that
 * invoice is cancelled, or taken back by an admin. The business's credit in
 * a currency is the sum of its movements; none is ever deleted.
 */
class SubscriptionCredit extends Model
{
    public const GRANTED  = 'granted';

    public const USED     = 'used';

    public const RETURNED = 'returned';

    public const REMOVED  = 'removed';

    public const KINDS    = [self::GRANTED, self::USED, self::RETURNED, self::REMOVED];

    protected $fillable = [
        'workspace_id',
        'currency',
        'amount',
        'kind',
        'subscription_invoice_id',
        'reason',
        'created_by',
    ];

    protected $casts    = [
        'amount' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }

    /** The admin who gave or took it back; null for what an invoice took or gave back. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
