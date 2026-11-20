<?php

namespace App\Models\User;

use App\Enums\FileCollection;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A collection of the business's products (Doc 19 section 19.11 - Phase B2),
 * for a promotion, a season, a brand or a theme of its own: its products -
 * those chosen, in their order, or, for a brand, every listed product of it -
 * its link, words, picture, first and last days (the business's) and SEO.
 * Active, or hidden from the storefronts. Customer groups' collections wait
 * for Doc 21.
 */
class CatalogCollection extends Model
{
    use HasFiles;
    use RecordsAuthors;

    public const BRAND = 'brand';

    public const KINDS = ['promotion', 'season', self::BRAND, 'theme'];

    public const ACTIVE = 'active';

    public const STATUSES = [self::ACTIVE, 'hidden'];

    /** How many products one holds by hand. */
    public const MAX_PRODUCTS = 500;

    protected $fillable = [
        'name',
        'slug',
        'kind',
        'item_brand_id',
        'description',
        'status',
        'available_from',
        'available_until',
        'seo_title',
        'seo_description',
        'position',
    ];

    protected $casts = [
        'item_brand_id'   => 'integer',
        'available_from'  => 'date',
        'available_until' => 'date',
        'position'        => 'integer',
    ];

    protected $attributes = [
        'status'   => self::ACTIVE,
        'position' => 0,
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ItemBrand::class, 'item_brand_id');
    }

    /** Its products chosen by hand, in their order. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(CatalogProduct::class, 'catalog_collection_products')->withPivot('position')->orderByPivot('position');
    }

    /** Its picture - one at most. */
    public function image(): MorphOne
    {
        return $this->fileIn(FileCollection::Image);
    }
}
