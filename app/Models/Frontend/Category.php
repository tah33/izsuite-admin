<?php

namespace App\Models\Frontend;

use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'is_system',
    ];

    protected $casts    = [
        'is_system' => 'boolean',
    ];

    /**
     * Subscriptions in this category.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
