<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item of a combo (Doc 04 section 14), and how many of it the combo holds. */
class ItemComponent extends Model
{
    protected $fillable = [
        'combo_id',
        'component_id',
        'quantity',
        'sort_order',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'sort_order' => 'integer',
    ];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'combo_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_id');
    }
}
