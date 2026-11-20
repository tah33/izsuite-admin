<?php

namespace App\Models\User;

use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A page of a storefront (Doc 19 section 19.6 - Phase C5a): its contact or
 * about page, or one of its four policies - its title, its words in rich text
 * (with headings), whether it is published, whether the footer links to it,
 * and its SEO. Each kind is at a link of its own, the same in every store.
 * From Phase C5b, a page of the business's own too, at a link it chooses, in
 * its place in the footer.
 */
class StorefrontPage extends Model
{
    use RecordsAuthors;

    public const PUBLISHED = 'published';

    public const DRAFT = 'draft';

    public const STATUSES = [self::PUBLISHED, self::DRAFT];

    public const CONTACT = 'contact';

    /** Phase C5b: a page of the business's own. */
    public const OWN = 'page';

    /** The pages every storefront may have, in the footer's order: the title each starts with, and its link. */
    public const STANDARD = [
        'about'    => ['About Us', 'about'],
        'contact'  => ['Contact Us', 'contact'],
        'shipping' => ['Shipping Policy', 'shipping-policy'],
        'returns'  => ['Return and Refund Policy', 'return-and-refund-policy'],
        'privacy'  => ['Privacy Policy', 'privacy-policy'],
        'terms'    => ['Terms and Conditions', 'terms-and-conditions'],
    ];

    protected $fillable = [
        'kind',
        'title',
        'slug',
        'body',
        'status',
        'in_footer',
        'seo_title',
        'seo_description',
        'position',
    ];

    protected $casts = [
        'storefront_id' => 'integer',
        'in_footer'     => 'boolean',
        'position'      => 'integer',
    ];

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }
}
