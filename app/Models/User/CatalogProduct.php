<?php

namespace App\Models\User;

use App\Enums\FileCollection;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * An item published online (Doc 19 sections 19.8 and 19.9 - Phase B1): what
 * a storefront shows of it and sells it by, beside the shared item itself -
 * its online title and link, descriptions, keywords, pictures and video
 * links, publication status, visibility and dates, sales method,
 * availability without Inventory, weight, SEO title and description, and the
 * storefronts it is on (every one, or those chosen).
 *
 * Draft while it is made, Scheduled until its first day - Published from it -
 * Published, Hidden from the storefronts, or Archived. Its dates are the
 * business's days.
 */
class CatalogProduct extends Model
{
    use HasFiles;
    use RecordsAuthors;

    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const PUBLISHED = 'published';

    public const HIDDEN = 'hidden';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::DRAFT, self::SCHEDULED, self::PUBLISHED, self::HIDDEN, self::ARCHIVED];

    /** Anyone; signed-in customers; only those given its link. Chosen customer groups wait for Doc 21. */
    public const VISIBILITIES = ['public', 'customers', 'link'];

    public const FIXED_PRICE = 'fixed_price';

    /** Sold at its price; by a request for a quotation; by asking for the price; not sold online. */
    public const SALES_METHODS = [self::FIXED_PRICE, 'quotation', 'contact', 'not_online'];

    public const AVAILABLE = 'available';

    public const LIMITED = 'limited';

    /** Without Inventory (19.25): as many as are asked for, none, or a quantity kept by hand. */
    public const AVAILABILITIES = [self::AVAILABLE, 'out_of_stock', self::LIMITED];

    public const WEIGHT_UNITS = ['kg', 'g', 'lb', 'oz'];

    /** How many pictures and video links one keeps. */
    public const MAX_PICTURES = 12;

    public const MAX_VIDEOS = 5;

    protected $fillable = [
        'title',
        'slug',
        'short_description',
        'description',
        'keywords',
        'status',
        'visibility',
        'available_from',
        'available_until',
        'sales_method',
        'availability',
        'stock_quantity',
        'weight',
        'weight_unit',
        'video_urls',
        // Phase B2: [{name, value}], for the storefront's filters.
        'custom_attributes',
        // Phase B3: [{name, values: [...]}] - none while it has no variants.
        'options',
        'seo_title',
        'seo_description',
        'all_storefronts',
    ];

    protected $casts = [
        'item_id'           => 'integer',
        'available_from'    => 'date',
        'available_until'   => 'date',
        'stock_quantity'    => 'decimal:3',
        'weight'            => 'decimal:3',
        'video_urls'        => 'array',
        'custom_attributes' => 'array',
        'options'           => 'array',
        'all_storefronts'   => 'boolean',
    ];

    /** Phase B2: how many custom attributes one keeps. */
    public const MAX_ATTRIBUTES = 20;

    /** Phase B3: how many options one names, values each option has, and variants it groups. */
    public const MAX_OPTIONS = 3;

    public const MAX_OPTION_VALUES = 30;

    public const MAX_VARIANTS = 100;

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'status'          => self::DRAFT,
        'visibility'      => 'public',
        'sales_method'    => self::FIXED_PRICE,
        'availability'    => self::AVAILABLE,
        'weight_unit'     => 'kg',
        'all_storefronts' => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The storefronts it is on, when not every one. */
    public function storefronts(): BelongsToMany
    {
        return $this->belongsToMany(Storefront::class, 'catalog_product_storefronts')->orderBy('storefronts.name');
    }

    /** Its pictures, oldest first, beside its item's image. */
    public function pictures(): MorphMany
    {
        return $this->filesIn(FileCollection::Gallery);
    }

    /** Phase B2: the collections that hold it by hand - a storefront's collection page lists them in its order (Phase C2). */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(CatalogCollection::class, 'catalog_collection_products')->withPivot('position');
    }

    /** Phase B3: the items it groups as its variants, in their order - its own item among them - while it has options. */
    public function variants(): HasMany
    {
        return $this->hasMany(CatalogProductVariant::class)->orderBy('position')->orderBy('id');
    }

    /** Its first picture: the Catalog page's thumbnail of an item without an image of its own. */
    public function cover(): MorphOne
    {
        return $this->fileIn(FileCollection::Gallery)->oldestOfMany();
    }

    /** Its status on the business's day $today: a scheduled one is published from its first day. */
    public function statusOn(string $today): string
    {
        return $this->status === self::SCHEDULED && $this->available_from !== null && $this->available_from->toDateString() <= $today
            ? self::PUBLISHED
            : $this->status;
    }
}
