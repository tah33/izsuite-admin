<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stage of a pipeline (Doc 17 sections 17.13 and 17.14 - Phase A2): its
 * place, the probability an opportunity in it starts with, and whether it
 * is open, won or lost - won always 100%, lost always 0%. The stale-after
 * period and the fields needed to enter it come with opportunities.
 */
class CrmStage extends Model
{
    public const OPEN = 'open';

    public const WON = 'won';

    public const LOST = 'lost';

    public const CATEGORIES = [self::OPEN => 'Open', self::WON => 'Won', self::LOST => 'Lost'];

    protected $fillable = [
        'name',
        'position',
        'probability',
        'category',
        'is_active',
    ];

    protected $casts = [
        'position'    => 'integer',
        'probability' => 'integer',
        'is_active'   => 'boolean',
    ];

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
