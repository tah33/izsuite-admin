<?php

namespace App\Models\User;

use App\Enums\FileCollection;
use App\Models\Concerns\HasFiles;
use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An online storefront of the business (Doc 19 section 19.4 - Phase A1): its
 * name and code - its address, unique across izSuite - its status, the
 * languages, currencies and countries it serves, each with its default, the
 * location it sells from, and who its customers ask for help.
 *
 * Draft while it is made, Live once it takes orders, Maintenance or
 * Suspended while it is closed for a while, Archived when it is kept for its
 * history. Only a Live storefront takes new orders.
 */
class Storefront extends Model
{
    use HasFiles;
    use RecordsAuthors;

    /** Phase C4a: how many pictures of its own its theme keeps to choose from. */
    public const MAX_BRANDING = 20;

    public const DRAFT = 'draft';

    public const LIVE = 'live';

    public const MAINTENANCE = 'maintenance';

    public const SUSPENDED = 'suspended';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::DRAFT, self::LIVE, self::MAINTENANCE, self::SUSPENDED, self::ARCHIVED];

    /**
     * Codes izSuite keeps for itself: a storefront's code becomes its
     * subdomain, so none of these may be one.
     */
    public const RESERVED_CODES = [
        'account', 'admin', 'api', 'app', 'assets', 'billing', 'blog', 'cdn', 'checkout', 'demo', 'dev', 'docs', 'ftp', 'help', 'imap',
        'izsuite', 'login', 'mail', 'mobile', 'ns1', 'ns2', 'pop', 'shop', 'smtp', 'staging', 'static', 'status', 'store', 'support', 'test', 'www',
    ];

    protected $fillable = [
        'name',
        'code',
        'status',
        'default_language',
        'languages',
        'currency',
        'currencies',
        'countries',
        'location_id',
        'support_email',
        'support_phone',
    ];

    protected $casts = [
        'languages'   => 'array',
        'currencies'  => 'array',
        'countries'   => 'array',
        'location_id' => 'integer',
        // Phase C4a: its theme as published, and as drafted (StorefrontTheme).
        'theme'              => 'array',
        'theme_draft'        => 'array',
        'theme_published_at' => 'datetime',
    ];

    // The column's default, so one just made reads as it was saved.
    protected $attributes = [
        'status' => self::DRAFT,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The location it sells from: its orders' location. */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Phase C4a: its own pictures - its theme's logo and favicon among them - oldest first. */
    public function brandingFiles(): MorphMany
    {
        return $this->filesIn(FileCollection::Branding);
    }

    /** Phase C5a: its pages - contact, about and its policies. */
    public function pages(): HasMany
    {
        return $this->hasMany(StorefrontPage::class);
    }
}
