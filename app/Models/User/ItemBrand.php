<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of a business's item brands (Doc 04 section 7): one list, a name used
 * once, shown only while the business has Brand Management on. Deleting one
 * leaves its items without a brand.
 */
class ItemBrand extends Model
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
        return $this->hasMany(Item::class, 'brand_id');
    }
}
