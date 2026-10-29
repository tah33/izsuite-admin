<?php

namespace App\Models\User;

use App\Enums\TaxTreatment;
use App\Models\Concerns\HasFiles;
use App\Services\User\Workspaces\WorkspaceSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer, a supplier, or both, of one business (Doc 03) - the one record
 * every app that deals with them points at, so the same contact is never kept
 * twice.
 *
 * Each has a Contact Number, C-00001 onwards within its business, given when
 * it is made and never given out again. Its addresses and tax country are
 * country codes (see App\Support\Countries); a shipping address marked "same
 * as billing" is the billing one, and its own fields stay empty. Its tax
 * treatment is one of Doc 11's (see TaxTreatment), or none. Its attachments
 * are files in the "attachments" collection.
 */
class Contact extends Model
{
    use HasFiles;

    public const TYPE_BUSINESS = 'business';

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPES = [self::TYPE_BUSINESS, self::TYPE_INDIVIDUAL];

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_SUPPLIER = 'supplier';

    public const ROLE_BOTH = 'both';

    public const ROLES = [self::ROLE_CUSTOMER, self::ROLE_SUPPLIER, self::ROLE_BOTH];

    /** The longest payment terms a contact can have, in days. */
    public const MAX_PAYMENT_TERMS = 365;

    /** The workspace_sequences counter its numbers come from. */
    public const NUMBER_SEQUENCE = 'contact';

    public const SHIPPING_FIELDS = [
        'shipping_line1', 'shipping_line2', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country',
    ];

    /** Everything but the number, which is given, never chosen. */
    protected $fillable = [
        'workspace_id',
        'contact_type',
        'role',
        'name',
        'trading_name',
        'contact_person',
        'job_title',
        'email',
        'phone',
        'alt_phone',
        'website',
        'preferred_language',
        'tags',
        'billing_line1',
        'billing_line2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'shipping_same_as_billing',
        ...self::SHIPPING_FIELDS,
        'sales_currency',
        'customer_payment_terms',
        'purchase_currency',
        'supplier_payment_terms',
        'tax_number',
        'tax_country',
        'tax_treatment',
        'tax_exemption_number',
        'notes',
        'is_active',
    ];

    /** The column defaults, so a contact just made reads the same as one fetched. */
    protected $attributes = [
        'contact_type'             => self::TYPE_BUSINESS,
        'role'                     => self::ROLE_CUSTOMER,
        'shipping_same_as_billing' => true,
        'is_active'                => true,
    ];

    protected $casts = [
        'tags'                     => 'array',
        'shipping_same_as_billing' => 'boolean',
        'customer_payment_terms'   => 'integer',
        'supplier_payment_terms'   => 'integer',
        'tax_treatment'            => TaxTreatment::class,
        'is_active'                => 'boolean',
    ];

    public static function formatNumber(int $number): string
    {
        return sprintf('C-%05d', $number);
    }

    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            $contact->contact_number ??= self::formatNumber(app(WorkspaceSequence::class)->next($contact->workspace_id, self::NUMBER_SEQUENCE));
        });
    }

    public function isCustomer(): bool
    {
        return $this->role !== self::ROLE_SUPPLIER;
    }

    public function isSupplier(): bool
    {
        return $this->role !== self::ROLE_CUSTOMER;
    }

    /**
     * The treatment its new sales take their tax from, ahead of the item's and
     * the business's (Doc 11 section 11): the one chosen for it - or Exempt,
     * with a tax exemption number and none chosen. Null for the standard one.
     */
    public function salesTaxTreatment(): ?TaxTreatment
    {
        $treatment = $this->tax_treatment ?? ($this->tax_exemption_number ? TaxTreatment::Exempt : null);

        return $treatment === TaxTreatment::Standard ? null : $treatment;
    }

    /** Those whose role is exactly $role - "customer" leaves out contacts that are both. */
    public function scopeOfRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    // ── Relationships ──

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The sales documents made out to it - any type, any status. */
    public function salesDocuments(): HasMany
    {
        return $this->hasMany(SalesDocument::class);
    }
}
