<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's lead assignment rules (Doc 17 section 17.9 - Phase
 * I1), in Settings → CRM's order (position). It fits a lead that has all it
 * asks for - its source, location, sales team, country (an ISO code) and one
 * of its interested items, those it names - and gives it to its person
 * (user_id), or to one of its sales team's members (team_id) by METHODS:
 * by turns, after the one it gave a lead to last (last_user_id), or to
 * whoever has the fewest open leads (LeadRouting).
 */
class CrmAssignmentRule extends Model
{
    public const ROUND_ROBIN = 'round_robin';

    public const WORKLOAD = 'workload';

    /** How a rule shares leads among a sales team's members. */
    public const METHODS = [self::ROUND_ROBIN, self::WORKLOAD];

    protected $fillable = [
        'name',
        'position',
        'is_active',
        'source_id',
        'location_id',
        'sales_team_id',
        'country',
        'item_id',
        'user_id',
        'team_id',
        'method',
        'last_user_id',
    ];

    protected $casts = [
        'position'      => 'integer',
        'is_active'     => 'boolean',
        'source_id'     => 'integer',
        'location_id'   => 'integer',
        'sales_team_id' => 'integer',
        'item_id'       => 'integer',
        'user_id'       => 'integer',
        'team_id'       => 'integer',
        'last_user_id'  => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmOption::class, 'source_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** The sales team a lead must name to fit. */
    public function salesTeam(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'sales_team_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The person it gives leads to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The sales team whose members it shares leads among. */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'team_id');
    }
}
