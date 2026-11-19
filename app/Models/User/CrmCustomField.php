<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a business's own fields for its leads or opportunities (Doc 17
 * Phase I4) - Settings → CRM, in its record's order (position): text, a
 * number, a date, a choice of its options, or yes or no; optional or
 * required; switched on or off. A record keeps its value under the field's
 * id (CustomFieldValues). Switched off, it leaves the forms and pages, and
 * its values stay.
 */
class CrmCustomField extends Model
{
    public const LEAD = 'lead';

    public const OPPORTUNITY = 'opportunity';

    /** Whose field it is. */
    public const RECORDS = [self::LEAD, self::OPPORTUNITY];

    public const TYPES = ['text', 'number', 'date', 'choice', 'yes_no'];

    protected $fillable = [
        'record',
        'name',
        'type',
        'options',
        'is_required',
        'is_active',
        'position',
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'position'    => 'integer',
    ];

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'is_required' => false,
        'is_active'   => true,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
