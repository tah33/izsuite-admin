<?php

namespace App\Models\Admin;

use App\Models\User\User;
use App\Support\DeviceName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in to the admin panel, or one attempt that failed (Doc 15 section
 * 15.2): when, from where, on which browser. Never changed once written.
 */
class AdminSignIn extends Model
{
    public const UPDATED_AT = null;

    /** Why an attempt failed, as the history words it. */
    public const FAILURES = [
        'password'     => 'Wrong e-mail or password',
        'switched_off' => 'Account switched off',
        'two_factor'   => 'Wrong two-factor code',
        'throttled'    => 'Too many attempts',
    ];

    protected $fillable = [
        'user_id',
        'email',
        'succeeded',
        'failure',
        'ip_address',
        'user_agent',
        'device',
    ];

    protected function casts(): array
    {
        return [
            'succeeded'  => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Signed in", or why it failed. */
    public function outcome(): string
    {
        return $this->succeeded ? 'Signed in' : (self::FAILURES[$this->failure] ?? 'Failed');
    }

    /** "Chrome on Windows", or the raw agent's start when it is not one DeviceName knows. */
    public function browser(): string
    {
        return DeviceName::from($this->user_agent) ?? ($this->user_agent ? mb_strimwidth($this->user_agent, 0, 40, '…') : 'Unknown browser');
    }
}
