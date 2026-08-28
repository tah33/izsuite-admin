<?php

namespace App\Models\User;

use App\Models\Admin\Role;
use App\Models\Billing\Invoice;
use App\Models\Billing\Subscription;
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
        'name',
        'headline',
        'bio',
        'email',
        'phone',
        'password',
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

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'last_login_at'               => 'datetime',
            'preferences'                 => 'array',
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
