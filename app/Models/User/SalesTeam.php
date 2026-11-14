<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A business's sales team (Doc 16 Phase A, Settings → Sales): a name, the
 * members in it, and whether it is in use. A sales document can name one;
 * a team switched off stays on the documents that name it. CRM extends it
 * later (Doc 17 section 17.24).
 */
class SalesTeam extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sales_team_members');
    }

    /** The sales documents that name it. */
    public function documents(): HasMany
    {
        return $this->hasMany(SalesDocument::class);
    }
}
