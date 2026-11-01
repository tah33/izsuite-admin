<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings > Expenses (Doc 11 section 17): a payee's payment terms when they
 * have none of their own, the notes a new expense starts with, and whether
 * recording one checks for a receipt entered before - on unless turned off.
 * Changing them leaves expenses already made as they are. A business has
 * none until it first saves them; an unsaved one reads the defaults, the same
 * as the columns'.
 */
class ExpenseSetting extends Model
{
    protected $fillable = [
        'payment_terms',
        'notes',
        'duplicate_check',
    ];

    protected $attributes = [
        'payment_terms'   => 30,
        'duplicate_check' => true,
    ];

    protected $casts = [
        'payment_terms'   => 'integer',
        'duplicate_check' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
