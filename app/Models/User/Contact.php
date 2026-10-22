<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer, a vendor, or both, of one business - the one record every app
 * that deals with them points at (Doc 01 section 10).
 */
class Contact extends Model
{
    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_VENDOR = 'vendor';

    protected $fillable = [
        'workspace_id',
        'is_customer',
        'is_vendor',
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'tax_number',
        'notes',
        'is_active',
    ];

    /** The column defaults, so a contact just made reads the same as one fetched. */
    protected $attributes = [
        'is_customer' => true,
        'is_vendor'   => false,
        'is_active'   => true,
    ];

    protected $casts = [
        'is_customer' => 'boolean',
        'is_vendor'   => 'boolean',
        'is_active'   => 'boolean',
    ];

    /** Customers or vendors only - a contact that is both is in each. */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where($type === self::TYPE_VENDOR ? 'is_vendor' : 'is_customer', true);
    }

    // ── Relationships ──

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
