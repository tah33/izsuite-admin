<?php

namespace App\Models\Admin;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A consent record (Doc 15 section 15.24 - Phase G3): an account accepting
 * the terms, or giving or withdrawing its consent to izSuite's product news
 * by e-mail - when, from where, and how. Written once, never changed.
 */
class ConsentRecord extends Model
{
    public const UPDATED_AT = null;

    public const TERMS     = 'terms';

    public const MARKETING = 'marketing';

    public const KINDS = [
        self::TERMS     => 'Terms and conditions',
        self::MARKETING => 'Product news by e-mail',
    ];

    /** Where it was given. */
    public const SOURCES = [
        'registration' => 'At sign-up',
        'invitation'   => 'Signing up from an invitation',
        'profile'      => 'In their profile',
    ];

    protected $fillable = [
        'user_id',
        'email',
        'kind',
        'given',
        'source',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'given'      => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
