<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of a business's item categories (Doc 04 section 6): a flat list, a name
 * used once. Deleting one leaves its items without a category.
 */
class ItemCategory extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'category_id');
    }
}
