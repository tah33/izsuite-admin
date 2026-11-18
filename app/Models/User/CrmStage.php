<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stage of a pipeline (Doc 17 sections 17.13 and 17.14 - Phase A2): its
 * place, the probability an opportunity in it starts with, and whether it
 * is open, won or lost - won always 100%, lost always 0%. Phase D3a adds,
 * for an open stage, the days after which an opportunity at it is stale,
 * and for any stage, the opportunity's details it needs before one enters.
 */
class CrmStage extends Model
{
    public const OPEN = 'open';

    public const WON = 'won';

    public const LOST = 'lost';

    public const CATEGORIES = [self::OPEN => 'Open', self::WON => 'Won', self::LOST => 'Lost'];

    /** The opportunity's details a stage can need before one enters it, by their words (Phase D3a). */
    public const REQUIRABLE = [
        'contact_person'   => 'Person to Deal With',
        'sales_team_id'    => 'Sales Team',
        'source_id'        => 'Lead Source',
        'priority'         => 'Priority',
        'next_action'      => 'Next Action',
        'requirements'     => 'Customer Requirements',
        'competitors'      => 'Competitors',
        'interested_items' => 'Interested Items',
    ];

    /** The longest stale-after period, in days. */
    public const MAX_STALE_DAYS = 365;

    protected $fillable = [
        'name',
        'position',
        'probability',
        'category',
        'stale_after_days',
        'required_fields',
        'is_active',
    ];

    protected $casts = [
        'position'         => 'integer',
        'probability'      => 'integer',
        'stale_after_days' => 'integer',
        'required_fields'  => 'array',
        'is_active'        => 'boolean',
    ];

    /** @return list<string> the details it needs, in REQUIRABLE's order */
    public function needs(): array
    {
        return array_values(array_intersect(array_keys(self::REQUIRABLE), $this->required_fields ?? []));
    }

    // The columns' defaults, so one just made reads as it was saved.
    protected $attributes = [
        'position'    => 1,
        'probability' => 0,
        'category'    => self::OPEN,
        'is_active'   => true,
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'crm_pipeline_id');
    }

    /** The probability a won stage has: 100; a lost one: 0; an open one: its own. */
    public static function probabilityFor(string $category, int $probability): int
    {
        return match ($category) {
            self::WON  => 100,
            self::LOST => 0,
            default    => $probability,
        };
    }
}
