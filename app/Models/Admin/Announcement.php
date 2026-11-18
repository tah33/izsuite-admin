<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A platform announcement (Doc 15 section 15.21 - Phase F1): a banner
 * between its days for its audience - in the workspace for the businesses
 * it reaches, or in the admin panel for izSuite's admins only - with a
 * priority, a link, and whether each person can dismiss it for themselves.
 * Emergency and legal ones are made not dismissible.
 */
class Announcement extends Model
{
    public const ALL        = 'all';

    public const PACKAGES   = 'packages';

    public const COUNTRIES  = 'countries';

    public const APPS       = 'apps';

    public const BUSINESSES = 'businesses';

    public const ADMINS     = 'admins';

    /** Who sees it. */
    public const AUDIENCES  = [
        self::ALL        => 'All businesses',
        self::PACKAGES   => 'Businesses on chosen plans',
        self::COUNTRIES  => 'Businesses in chosen countries',
        self::APPS       => 'Businesses with chosen apps on',
        self::BUSINESSES => 'Businesses named',
        self::ADMINS     => 'izSuite admins only',
    ];

    /** Most urgent last: shown first. */
    public const PRIORITIES = ['info' => 'Information', 'important' => 'Important', 'emergency' => 'Emergency'];

    public const STATES     = ['scheduled' => 'Scheduled', 'live' => 'Live', 'ended' => 'Ended'];

    protected $fillable = [
        'title',
        'message',
        'audience',
        'targets',
        'priority',
        'link_url',
        'link_label',
        'dismissible',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'targets'     => 'array',
        'dismissible' => 'boolean',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
    ];

    /** Scheduled (before its start), live, or ended (from its end). */
    public function stateAt(Carbon $now): string
    {
        return match (true) {
            $this->starts_at !== null && $this->starts_at->gt($now) => 'scheduled',
            $this->ends_at !== null && $this->ends_at->lte($now)     => 'ended',
            default                                                 => 'live',
        };
    }

    /** How urgent, for ordering: emergency first. */
    public function urgency(): int
    {
        return (int) array_search($this->priority, array_keys(self::PRIORITIES), true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(AnnouncementDismissal::class);
    }
}
