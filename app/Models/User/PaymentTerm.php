<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's payment terms (Doc 11 section 14): a name and the days
 * a document is due after its date - "30 Days". One shared list for sales,
 * purchases and expenses, each name and each number of days used once.
 *
 * Contacts, defaults and documents keep the days, not the term, so a term is
 * in use while any of them has its days: it is then switched off rather than
 * deleted, and its days stay as they are. Switched off, it is offered to
 * nothing new. Any other number of days stays possible, as Custom.
 */
class PaymentTerm extends Model
{
    /** What a business starts with (Doc 11 section 14), name => days - its own to rename, switch off or add to. */
    public const STARTER = ['Due on Receipt' => 0, '7 Days' => 7, '15 Days' => 15, '30 Days' => 30, '45 Days' => 45, '60 Days' => 60];

    protected $fillable = [
        'name',
        'days',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'days'      => 'integer',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
