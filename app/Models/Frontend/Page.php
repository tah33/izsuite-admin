<?php

namespace App\Models\Frontend;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'show_in_footer',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'show_in_footer' => 'boolean',
        ];
    }

    // ── Relationships ──

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFooter($query)
    {
        return $query->where('show_in_footer', true)
            ->where('status', 'published')
            ->orderBy('sort_order');
    }
}
