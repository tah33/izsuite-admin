<?php

namespace App\Models\User;

use App\Enums\FileCollection;
use App\Models\Concerns\HasFiles;
use App\Services\User\Workspaces\WorkspaceSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A product or service one business sells or buys (Doc 04) - the one record
 * every app that deals with it points at. Prices are in the business's base
 * currency.
 *
 * Each has an Item Number, I-00001 onwards within its business, given when it
 * is made and never given out again. Its usage - sales, purchase or both -
 * decides where it can be picked. A combo is sold as a package of two or more
 * other items, its components; it is never part of another combo itself.
 *
 * Its image and a digital product's files are files in the "image" and
 * "digital_files" collections.
 */
class Item extends Model
{
    use HasFiles;

    public const TYPE_PHYSICAL = 'physical';

    public const TYPE_DIGITAL = 'digital';

    public const TYPE_SERVICE = 'service';

    public const TYPE_COMBO = 'combo';

    public const TYPES = [self::TYPE_PHYSICAL, self::TYPE_DIGITAL, self::TYPE_SERVICE, self::TYPE_COMBO];

    public const USAGE_SALES = 'sales';

    public const USAGE_PURCHASE = 'purchase';

    public const USAGE_BOTH = 'both';

    public const USAGES = [self::USAGE_SALES, self::USAGE_PURCHASE, self::USAGE_BOTH];

    public const DELIVERY_METHODS = ['manual', 'download', 'license_key', 'external_link'];

    public const WARRANTY_UNITS = ['days', 'months', 'years'];

    public const WARRANTY_START_RULES = ['invoice_date', 'delivery_date', 'manual_activation'];

    /** A combo's price: one of its own, or what its components add up to. */
    public const COMBO_PRICING = ['fixed', 'sum'];

    /** What a document shows of a combo: the combo alone, or its components under it. */
    public const COMBO_DISPLAY = ['combo_only', 'combo_and_components'];

    /** The workspace_sequences counter its numbers come from. */
    public const NUMBER_SEQUENCE = 'item';

    /** Everything but the number, which is given, never chosen. */
    protected $fillable = [
        'workspace_id',
        'type',
        'usage',
        'name',
        'sku',
        'barcode',
        'unit',
        'category_id',
        'brand_id',
        'description',
        'sale_price',
        'sales_tax_id',
        'sales_description',
        'purchase_price',
        'purchase_tax_id',
        'purchase_description',
        'delivery_method',
        'delivery_instructions',
        'warranty_available',
        'warranty_period',
        'warranty_unit',
        'warranty_start_rule',
        'warranty_terms',
        'combo_pricing',
        'combo_display',
        'is_active',
    ];

    /** The column defaults, so an item just made reads the same as one fetched. */
    protected $attributes = [
        'type'               => self::TYPE_PHYSICAL,
        'usage'              => self::USAGE_BOTH,
        'warranty_available' => false,
        'is_active'          => true,
    ];

    protected $casts = [
        'category_id'        => 'integer',
        'brand_id'           => 'integer',
        'sale_price'         => 'decimal:2',
        'sales_tax_id'       => 'integer',
        'purchase_price'     => 'decimal:2',
        'purchase_tax_id'    => 'integer',
        'warranty_available' => 'boolean',
        'warranty_period'    => 'integer',
        'is_active'          => 'boolean',
    ];

    public static function formatNumber(int $number): string
    {
        return sprintf('I-%05d', $number);
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $item->item_number ??= self::formatNumber(app(WorkspaceSequence::class)->next($item->workspace_id, self::NUMBER_SEQUENCE));
        });
    }

    public function isCombo(): bool
    {
        return $this->type === self::TYPE_COMBO;
    }

    /** Whether it can be picked on a sale. */
    public function isSold(): bool
    {
        return $this->usage !== self::USAGE_PURCHASE;
    }

    /**
     * What a combo's components add up to at their sales prices today - its
     * price when it is priced as their sum. Worked out exactly in decimal
     * (bcmath; floats get 1.5 × 10.01 wrong) and rounded half up to the cent
     * once, at the end. A component without a sales price counts as zero.
     *
     * Null for an item that is not a combo. Expects its components loaded
     * with their items.
     */
    public function componentTotal(): ?string
    {
        if (! $this->isCombo()) {
            return null;
        }

        $total = $this->components->reduce(
            fn (string $sum, ItemComponent $row) => bcadd($sum, bcmul((string) ($row->component?->sale_price ?? '0'), (string) $row->quantity, 5), 5),
            '0',
        );

        return bcadd($total, '0.005', 2);
    }

    // ── Relationships ──

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ItemBrand::class);
    }

    /** The tax a new sale of it starts with (Doc 04 section 8). */
    public function salesTax(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'sales_tax_id');
    }

    /** The tax a new purchase of it starts with (Doc 04 section 9). */
    public function purchaseTax(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'purchase_tax_id');
    }

    /** Its picture (Doc 04 section 6) - one at most. */
    public function image(): MorphOne
    {
        return $this->fileIn(FileCollection::Image);
    }

    /** A combo's components, in the order they were given. */
    public function components(): HasMany
    {
        return $this->hasMany(ItemComponent::class, 'combo_id')->orderBy('sort_order')->orderBy('id');
    }

    /** The combos this item is a component of. */
    public function usedInCombos(): HasMany
    {
        return $this->hasMany(ItemComponent::class, 'component_id');
    }

    /** The sales document lines it is on - any document, any status. */
    public function salesLines(): HasMany
    {
        return $this->hasMany(SalesDocumentLine::class);
    }
}
