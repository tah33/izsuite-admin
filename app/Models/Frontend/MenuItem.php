<?php

namespace App\Models\Frontend;

use App\Enums\Edition;
use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry of the business sidebar (see the menu_items migration).
 */
class MenuItem extends Model
{
    use BumpsCatalogVersion;

    public const SECTION_CORE = 'core';

    public const SECTION_APPS = 'apps';

    public const SECTION_BOTTOM = 'bottom';

    public const SECTIONS = [
        self::SECTION_CORE   => 'Core Workspace',
        self::SECTION_APPS   => 'App family',
        self::SECTION_BOTTOM => 'Bottom',
    ];

    protected $fillable = [
        'parent_id',
        'section',
        'family_key',
        'min_edition',
        'label',
        'icon',
        'route',
        'sort_order',
        'permission_key',
        'show_if',
        'is_active',
    ];

    protected $casts = [
        'parent_id'   => 'integer',
        'min_edition' => Edition::class,
        'sort_order'  => 'integer',
        'is_active'   => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
