<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A branch, shop, office or warehouse of one business (workspace), Doc 12
 * section 17: its code - unique in the business - address, contacts and time
 * zone, the lines its documents add to their header, and whether it is
 * active. One location is the business's Primary; an inactive one takes no
 * new transactions, but keeps the ones it has.
 */
class Location extends Model
{
    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'code',
        'is_primary',
        'is_active',
        'phone',
        'email',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
        'document_header',
    ];

    protected $attributes = [
        'is_primary' => false,
        'is_active'  => true,
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // ── Relationships ──

    /** The account that owns the business. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The business it belongs to. */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The users with Selected Locations it was chosen for (Doc 12 section 20). */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'workspace_member_locations');
    }
}
