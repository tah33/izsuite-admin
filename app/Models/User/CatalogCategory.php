<?php

namespace App\Models\User;

use App\Enums\FileCollection;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * An item category as a storefront shows it (Doc 19 section 19.11 - Phase
 * B2): its link - its own in the business - its words, picture and SEO
 * title and description, whether the storefront's menu lists it, and where.
 * The category itself is the shared Items' own.
 */
class CatalogCategory extends Model
{
    use HasFiles;
    use RecordsAuthors;

    protected $fillable = [
        'slug',
        'description',
        'seo_title',
        'seo_description',
        'in_menu',
        'position',
    ];

    protected $casts = [
        'item_category_id' => 'integer',
        'in_menu'          => 'boolean',
        'position'         => 'integer',
    ];

    protected $attributes = [
        'in_menu'  => true,
        'position' => 0,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    /** Its picture - one at most. */
    public function image(): MorphOne
    {
        return $this->fileIn(FileCollection::Image);
    }
}
