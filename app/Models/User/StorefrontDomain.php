<?php

namespace App\Models\User;

use App\Models\Concerns\RecordsAuthors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A business's own domain for one of its storefronts (Doc 19 section 19.5 -
 * Phase A3): waiting until its TXT record proves the business holds it, then
 * verified. Only a verified domain becomes the storefront's primary one -
 * the address its others redirect to; with none, its izSuite address is.
 */
class StorefrontDomain extends Model
{
    use RecordsAuthors;

    /** The name of the TXT record that proves a domain is the business's: under the domain itself. */
    public const RECORD_PREFIX = '_izsuite-verify.';

    protected $fillable = [
        'host',
        'token',
        'verified_at',
        'checked_at',
        'is_primary',
    ];

    protected $casts = [
        'storefront_id' => 'integer',
        'verified_at'   => 'datetime',
        'checked_at'    => 'datetime',
        'is_primary'    => 'boolean',
    ];

    protected $attributes = [
        'is_primary' => false,
    ];

    public function storefront(): BelongsTo
    {
        return $this->belongsTo(Storefront::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** "_izsuite-verify.shop.example.com". */
    public function recordName(): string
    {
        return self::RECORD_PREFIX.$this->host;
    }

    /** "izsuite-verify=…". */
    public function recordValue(): string
    {
        return 'izsuite-verify='.$this->token;
    }
}
