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

    protected $casts            = [
        'credentials' => 'array',
        'is_active'   => 'boolean',
        'is_sandbox'  => 'boolean',
    ];

    /**
     * Each online gateway defines which credential fields it needs.
     */
    public const GATEWAY_FIELDS = [
        'stripe'   => [
            'publishable_key' => 'Publishable Key',
            'secret_key'      => 'Secret Key',
            'webhook_secret'  => 'Webhook Secret',
        ],
        'paypal'   => [
            'client_id'     => 'Client ID',
            'client_secret' => 'Client Secret',
            'webhook_id'    => 'Webhook ID',
        ],
        'paddle'   => [
            'vendor_id'   => 'Vendor ID',
            'vendor_auth' => 'Auth Code',
            'public_key'  => 'Public Key',
        ],
        'paystack' => [
            'public_key' => 'Public Key',
            'secret_key' => 'Secret Key',
        ],
        'razorpay' => [
            'key_id'     => 'Key ID',
            'key_secret' => 'Key Secret',
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

    // ── Accessors ──

    /**
     * Get the expected credential fields for this gateway.
     */
    public function getCredentialFieldsAttribute(): array
    {
        return self::GATEWAY_FIELDS[$this->slug] ?? [];
    }

    /**
     * Get a specific credential value.
     */
    public function credential(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }
}
