<?php

namespace App\Models\User;

use App\Models\Admin\Role;
use App\Models\Billing\Invoice;
use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $fillable = [
        'prefix',
        'first_name',
        'last_name',
        'username',
        'headline',
        'bio',
        'email',
        'phone',
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
            'terms_accepted_at' => 'datetime',
            'password'          => 'hashed',
            'last_login_at'     => 'datetime',
            'preferences'       => 'array',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    public function isAdmin(): bool
    {
        return $this->role_id === Role::SUPER_ADMIN_ID
            || ($this->role && in_array($this->role->slug, ['super-admin', 'admin', 'staff'], true));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role_id === Role::SUPER_ADMIN_ID;
    }

    public function hasPermission(string $routeName): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role?->hasPermission($routeName) ?? false;
    }
}
