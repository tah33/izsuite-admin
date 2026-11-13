<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable         = [
        'type',
        'name',
        'slug',
        'logo_url',
        'description',
        'instructions',
        'credentials',
        'is_active',
        'is_sandbox',
        'sort_order',
    ];

    // An online gateway's keys, kept encrypted (Doc 15 section 15): ['test' => [...], 'live' => [...]].
    protected $casts            = [
        'credentials' => 'encrypted:array',
        'is_active'   => 'boolean',
        'is_sandbox'  => 'boolean',
    ];

    protected $hidden           = ['credentials'];

    /** The online gateways izSuite takes payments through (Doc 13 Phase D2). The others stay hidden until they are built. */
    public const BUILT          = ['stripe'];

    /** Each gateway's test and live modes keep their own keys; is_sandbox picks test. */
    public const MODES          = ['test', 'live'];

    /** The keys each built gateway takes, in each mode, and what the admin calls them. */
    public const GATEWAY_FIELDS = [
        'stripe' => [
            'publishable_key' => 'Publishable key',
            'secret_key'      => 'Secret key',
            'webhook_secret'  => 'Webhook signing secret',
        ],
    ];

    // ── Scopes ──

    public function scopeOnline($query)
    {
        return $query->where('type', 'online');
    }

    public function scopeOffline($query)
    {
        return $query->where('type', 'offline');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether it takes real payments: switched on, and a gateway in live
     * mode. Changing one that does is a critical action (Doc 15 section 15.25).
     */
    public function takesRealPayments(): bool
    {
        return (bool) $this->is_active && ($this->type !== 'online' || ! $this->is_sandbox);
    }

    // ── Accessors ──

    /**
     * Get the expected credential fields for this gateway.
     */
    public function getCredentialFieldsAttribute(): array
    {
        return self::GATEWAY_FIELDS[$this->slug] ?? [];
    }

    /** The keys of the mode it is in: test while in sandbox, else live. */
    public function keys(): array
    {
        return $this->credentials[$this->is_sandbox ? 'test' : 'live'] ?? [];
    }

    /** A kept key as the admin sees it again - its prefix and last four, "sk_test_…4242" - never whole. */
    public function masked(string $mode, string $key): ?string
    {
        $value  = $this->credentials[$mode][$key] ?? null;

        if (! $value) {
            return null;
        }

        $prefix = preg_match('/^[a-z]+_(test_|live_)?/', $value, $match) ? $match[0] : '';

        return $prefix.'…'.substr($value, -4);
    }
}
