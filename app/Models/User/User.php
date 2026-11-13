<?php

namespace App\Models\User;

use App\Models\Admin\Role;
use App\Models\Billing\Invoice;
use App\Models\Billing\Subscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** An account that signs in. */
    public const ACTIVE = 'active';

    /** Switched off: a staff member in Admin > Staff. */
    public const INACTIVE = 'inactive';

    /** Locked by izSuite support (Doc 15 section 15.7) until it is unlocked. */
    public const LOCKED = 'locked';

    /**
     * HasFactory guesses Database\Factories\User\UserFactory from this
     * model's App\Models\User namespace, but the factory lives one level up.
     * Without this, User::factory() throws "class not found".
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'prefix',
        'first_name',
        'last_name',
        'username',
        'headline',
        'bio',
        'email',
        'phone',
        'language',
        'password',
        'terms_accepted_at',
        'role_id',
        'timezone',
        'currency',
        'avatar',
        'status',
        'last_login_at',
        'email_verified_at',
        'preferences',
    ];

    protected $hidden   = [
        'password',
        'remember_token',

        // Hashed, but still a live credential while it lasts, and nothing
        // outside EmailVerificationService has any reason to read it.
        'verification_otp',
        'verification_otp_expires_at',

        // The authenticator app's secret and the recovery codes' hashes (Doc 12
        // section 22): only TwoFactorService reads them.
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_last_step',
    ];

    /**
     * `name` is no longer a column, but it is the natural thing to display and
     * is read in views, emails, PDFs and activity-log messages. Appending it
     * keeps toArray()/toJson() output the same shape as before the split.
     */
    protected $appends  = [
        'name',
    ];

    /**
     * Full name, composed from the two columns that replaced `name`.
     *
     * Read-only on purpose: writing a single blob back would have to guess
     * where the first name ends, so callers set first_name/last_name directly.
     */
    protected function name(): Attribute
    {
        return Attribute::get(
            fn (): string => trim($this->first_name.' '.$this->last_name)
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_otp_expires_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password'          => 'hashed',
            'last_login_at'     => 'datetime',
            'preferences'       => 'array',
            'two_factor_secret'         => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at'   => 'datetime',
            'two_factor_last_step'      => 'integer',
            'sessions_valid_from'       => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Business settings, locations and staff each belong to one of the
    // account's workspaces; these reach across all of them. The APIs go
    // through the workspace instead (see Workspace).

    public function businessSettings(): HasMany
    {
        return $this->hasMany(BusinessSetting::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /** The account's places in businesses, its own among them. */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function userStaff(): HasMany
    {
        return $this->hasMany(UserStaff::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function currentPlanSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->ofMany(['id' => 'max'], function ($query) {
                $query->whereNotNull('plan_id')
                    ->where('status', 'active');
            });
    }

    /**
     * Whether the account is izSuite staff: the Super Admin, or any role but a
     * website account's (Role::FRONTEND_SLUGS). That takes in the roles made
     * in Admin > Roles, whatever they are called (Doc 15 section 15.3).
     */
    public function isAdmin(): bool
    {
        return $this->role_id === Role::SUPER_ADMIN_ID
            || ($this->role && ! $this->role->isFrontend());
    }

    public function isSuperAdmin(): bool
    {
        return $this->role_id === Role::SUPER_ADMIN_ID;
    }

    /**
     * The plain frontend account role - not an admin, and not one of the other
     * non-admin roles (recruiter, candidate) that share the users table.
     */
    public function isUser(): bool
    {
        return $this->role?->slug === 'user';
    }

    public function hasPermission(string $routeName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role?->hasPermission($routeName) ?? false;
    }
}
