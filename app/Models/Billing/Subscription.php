<?php

namespace App\Models\Billing;

use App\Models\Frontend\Category;
use App\Models\User\User;
use App\Models\User\UserConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_slug',
        'category_id',
        'name',
        'description',
        'logo_url',
        'website_url',
        'amount',
        'currency',
        'payment_id',
        'payment_method_slug',
        'job_postings_limit',
        'job_postings_used',
        'ai_screenings_limit',
        'ai_screenings_used',
        'team_members_limit',
        'team_members_used',
        'billing_cycle',
        'billing_day',
        'start_date',
        'next_renewal_date',
        'last_charged_date',
        'cancelled_at',
        'status',
        'usage_status',
        'confidence_score',
        'is_manual',
        'connection_id',
    ];

    protected $casts    = [
        'amount'              => 'decimal:2',
        'plan_id'             => 'integer',
        'job_postings_limit'  => 'integer',
        'job_postings_used'   => 'integer',
        'ai_screenings_limit' => 'integer',
        'ai_screenings_used'  => 'integer',
        'team_members_limit'  => 'integer',
        'team_members_used'   => 'integer',
        'start_date'          => 'date',
        'next_renewal_date'   => 'date',
        'last_charged_date'   => 'date',
        'cancelled_at'        => 'datetime',
        'confidence_score'    => 'integer',
        'is_manual'           => 'boolean',
    ];

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(UserConnection::class, 'connection_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByUsageStatus($query, string $usageStatus)
    {
        return $query->where('usage_status', $usageStatus);
    }

    public function scopeUpcomingRenewals($query, int $days = 30)
    {
        return $query->active()
            ->whereNotNull('next_renewal_date')
            ->whereBetween('next_renewal_date', [now(), now()->addDays($days)])
            ->orderBy('next_renewal_date');
    }

    public function scopeLeaks($query)
    {
        return $query->active()
            ->whereIn('usage_status', ['low', 'unused']);
    }

    // ── Accessors ──

    /**
     * Get the monthly cost (normalized from any billing cycle).
     */
    public function getMonthlyCostAttribute(): float
    {
        return match ($this->billing_cycle) {
            'weekly'    => $this->amount * 4.33,
            'monthly'   => $this->amount,
            'quarterly' => $this->amount / 3,
            'yearly'    => $this->amount / 12,
            default     => $this->amount,
        };
    }
}
