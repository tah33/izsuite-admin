<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPaymentProvider extends Model
{
    protected $fillable = [
        'plan_id',
        'provider',
        'interval',
        'provider_price_id',
    ];

    // ── Relationships ──

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
