<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credit applied to an issued invoice (Doc 16 sections 16.15 and 16.18): part
 * of a customer advance, or of a credit note's remaining credit, in the
 * invoice's currency. Taking one off marks it removed with a reason, so its
 * history stays.
 */
class SalesAllocation extends Model
{
    public const ACTIVE = 'active';

    public const REMOVED = 'removed';

    protected $fillable = [
        'invoice_id',
        'customer_advance_id',
        'credit_note_id',
        'amount',
        'allocation_date',
        'status',
        'removed_at',
        'removed_by',
        'removal_reason',
        'created_by',
    ];

    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    protected $casts = [
        'invoice_id'          => 'integer',
        'customer_advance_id' => 'integer',
        'credit_note_id'      => 'integer',
        'amount'              => 'decimal:2',
        'removed_at'          => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** What a message names its source by: "advance ADV-00001", "credit note CN-00001". */
    public function getSourceLabelAttribute(): string
    {
        return $this->customer_advance_id ? "advance {$this->advance?->number}" : "credit note {$this->creditNote?->number}";
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'invoice_id');
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(CustomerAdvance::class, 'customer_advance_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'credit_note_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
