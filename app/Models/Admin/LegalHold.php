<?php

namespace App\Models\Admin;

use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A legal hold on a business (Doc 15 section 15.24 - Phase G2): while it
 * lasts, nothing of the business can be permanently deleted - the business
 * itself, or its records in the app. Released with a reason; kept after as
 * history, with the business's name.
 */
class LegalHold extends Model
{
    protected $fillable = [
        'workspace_id',
        'business_name',
        'reference',
        'reason',
        'placed_by',
        'placed_at',
        'released_by',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'placed_at'   => 'datetime',
        'released_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function placer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
