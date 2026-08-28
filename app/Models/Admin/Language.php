<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'name',
        'code',
        'native_name',
        'is_active',
        'is_default',
        'direction',
    ];

    protected $casts    = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
