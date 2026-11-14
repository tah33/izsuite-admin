<?php

namespace App\Models\Billing;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A promotion code (Doc 15 section 15.17 - Phase C3b): a percent or a fixed
 * amount off a new subscription's first invoice, before credits and tax -
 * for the plans, cycles and billing countries it names (none named, all),
 * between its days, up to its total and per-business uses, new customers
 * only if set. Paused, it takes nothing off; used, it is never deleted.
 */
class Promotion extends Model
{
    public const PERCENT = 'percent';

    public const FIXED   = 'fixed';

    public const ACTIVE  = 'active';

    public const PAUSED  = 'paused';

    protected $fillable = [
        'name',
        'code',
        'discount_type',
        'discount_value',
        'currency',
        'packages',
        'cycles',
        'countries',
        'starts_on',
        'ends_on',
        'max_redemptions',
        'per_business',
        'new_customers_only',
        'status',
        'created_by',
    ];

    protected $casts    = [
        'discount_value'     => 'decimal:2',
        'packages'           => 'array',
        'cycles'             => 'array',
        'countries'          => 'array',
        'starts_on'          => 'date',
        'ends_on'            => 'date',
        'max_redemptions'    => 'integer',
        'per_business'       => 'integer',
        'new_customers_only' => 'boolean',
    ];

    /** "20% off" or "USD 5.00 off". */
    public function describe(): string
    {
        return $this->discount_type === self::PERCENT
            ? rtrim(rtrim((string) $this->discount_value, '0'), '.').'% off'
            : "{$this->currency} ".number_format((float) $this->discount_value, 2).' off';
    }

    /** Where it stands on $day, $used times taken: paused, not started, ended, used up, or active. */
    public function statusOn(Carbon|string $day, int $used): string
    {
        $day = Carbon::parse($day)->startOfDay();

        return match (true) {
            $this->status === self::PAUSED                                      => self::PAUSED,
            $this->starts_on !== null && $this->starts_on->gt($day)             => 'scheduled',
            $this->ends_on !== null && $this->ends_on->lt($day)                 => 'ended',
            $this->max_redemptions !== null && $used >= $this->max_redemptions  => 'used_up',
            default                                                             => self::ACTIVE,
        };
    }

    /** The invoices it took something off - cancelled ones too. */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
