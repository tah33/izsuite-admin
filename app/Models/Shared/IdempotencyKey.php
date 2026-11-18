<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A create request's key and its first answer (Doc 14 section 22 - Phase
 * A): a repeat with the same key gets that answer back instead of a second
 * record. Kept a day.
 */
class IdempotencyKey extends Model
{
    use MassPrunable;

    /** Hours a key's answer is kept. */
    public const HOURS = 24;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'key',
        'route',
        'fingerprint',
        'status_code',
        'response',
    ];

    protected $casts    = [
        'status_code' => 'integer',
    ];

    public function answered(): bool
    {
        return $this->status_code !== null;
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(self::HOURS));
    }
}
