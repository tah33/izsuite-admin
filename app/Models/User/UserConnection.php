<?php

namespace App\Models\User;

use App\Models\Billing\Subscription;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserConnection extends Model
{
    protected $fillable = [
        'user_id',
        'integration_id',
        'status',
        'account_identifier',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'metadata',
        'last_synced_at',
        'items_synced',
    ];

    protected $casts    = [
        'metadata'         => 'array',
        'token_expires_at' => 'datetime',
        'last_synced_at'   => 'datetime',
        'items_synced'     => 'integer',
    ];

    /**
     * Encrypt tokens when setting.
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? encrypt($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? encrypt($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? decrypt($value) : null;
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'connection_id');
    }

    // ── Scopes ──

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    // ── Accessors ──

    public function getIsConnectedAttribute(): bool
    {
        return $this->status === 'connected';
    }

    public function getIsTokenExpiredAttribute(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
