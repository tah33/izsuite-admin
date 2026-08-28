<?php

namespace App\Models\Frontend;

use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    protected $fillable               = [
        'section',
        'data',
        'sort_order',
    ];

    protected $casts                  = [
        'data' => 'array',
    ];

    // ── Section constants ──

    public const SECTION_STATS        = 'stats';

    public const SECTION_FEATURES     = 'features';

    public const SECTION_HOW_IT_WORKS = 'how_it_works';

    public const SECTION_TESTIMONIALS = 'testimonials';

    /**
     * Get all items for a given section, ordered by sort_order.
     */
    public static function forSection(string $section)
    {
        return static::where('section', $section)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Helper to get values from the JSON data attribute.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! is_array($this->data)) {
            return $default;
        }

        return $this->data[$key] ?? $default;
    }
}
