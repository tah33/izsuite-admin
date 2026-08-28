<?php

namespace App\Models;

use App\Models\User\UserConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_url',
        'category',
        'auth_type',
        'auth_config',
        'is_active',
        'sort_order',
    ];

    protected $casts    = [
        'auth_config' => 'array',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    // ── Relationships ──

    public function userConnections(): HasMany
    {
        return $this->hasMany(UserConnection::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ── Helpers ──

    /**
     * Category labels for display.
     */
    public static function categoryLabels(): array
    {
        return [
            'payment_platforms'          => 'Payment Platforms',
            'productivity_notifications' => 'Productivity & Notifications',
            'email'                      => 'Email Services',
            'cloud_storage'              => 'Cloud & Storage',
            'other'                      => 'Other Services',
        ];
    }

    /**
     * Get the display label for this integration's category.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::categoryLabels()[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category));
    }
}
