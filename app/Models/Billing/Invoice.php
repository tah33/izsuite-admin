<?php

namespace App\Models\Billing;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_id',
        'transaction_id',
        'proof_image',
        'paid_at',
        'description',
    ];

    protected $casts    = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    // ── Scopes ──

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // ── Helpers ──

    /**
     * Generate the next invoice number.
     */
    public static function generateNumber(): string
    {
        $last = static::orderByDesc('id')->value('invoice_number');

        if ($last && preg_match('/INV-(\d+)/', $last, $matches)) {
            $next = (int) $matches[1] + 1;
        } else {
            $next = 1;
        }

        return 'INV-'.str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
