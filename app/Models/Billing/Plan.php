<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'plan_for',
        'billing_type',
        'description',
        'monthly_price',
        'yearly_price',
        'trial_days',
        'features',
        'job_postings_limit',
        'ai_screenings_limit',
        'team_members_limit',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected $casts    = [
        'monthly_price'       => 'decimal:2',
        'yearly_price'        => 'decimal:2',
        'trial_days'          => 'integer',
        'features'            => 'array',
        'job_postings_limit'  => 'integer',
        'ai_screenings_limit' => 'integer',
        'team_members_limit'  => 'integer',
        'is_active'           => 'boolean',
        'is_featured'         => 'boolean',
        'sort_order'          => 'integer',
    ];

    // ── Relationships ──

    public function paymentProviders(): HasMany
    {
        return $this->hasMany(PlanPaymentProvider::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
