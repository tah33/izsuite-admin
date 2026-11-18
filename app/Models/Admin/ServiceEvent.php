<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Something an outside service did (Doc 15 section 15.23 - Phase D3a): a
 * card provider's webhook taken or refused, or a call to it that failed -
 * for Admin > Service Health. Pruned after DAYS.
 */
class ServiceEvent extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    public const DAYS     = 30;

    public const STRIPE   = 'stripe';

    public const WEBHOOK  = 'webhook';

    public const CALL     = 'call';

    protected $fillable = [
        'service',
        'kind',
        'ok',
        'message',
    ];

    protected $casts    = [
        'ok'         => 'boolean',
        'created_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::DAYS));
    }
}
