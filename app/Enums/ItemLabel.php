<?php

namespace App\Enums;

use App\Models\User\ItemBrand;
use App\Models\User\ItemCategory;

/**
 * The two lists of names a business sorts its items by (Doc 04 sections 6 and
 * 7): categories and brands. Each is one flat list per business, a name used
 * once, kept under Settings > Items; both work the same way.
 *
 * The value is the list's part of its address: /item-categories, /item-brands.
 */
enum ItemLabel: string
{
    case Category = 'categories';
    case Brand = 'brands';

    /** @return class-string<ItemCategory|ItemBrand> */
    public function model(): string
    {
        return match ($this) {
            self::Category => ItemCategory::class,
            self::Brand    => ItemBrand::class,
        };
    }

    /** The column of items that points at one. */
    public function itemColumn(): string
    {
        return match ($this) {
            self::Category => 'category_id',
            self::Brand    => 'brand_id',
        };
    }

    /** What one is called in messages and the activity log. */
    public function noun(): string
    {
        return match ($this) {
            self::Category => 'category',
            self::Brand    => 'brand',
        };
    }

    public function label(): string
    {
        return ucfirst($this->noun());
    }
}
