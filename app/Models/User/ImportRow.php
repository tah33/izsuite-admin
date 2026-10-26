<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of an import's file, as it was checked: its line (the header is
 * line 1), its values as the API takes them, what is wrong with it, and the
 * records - or earlier rows - it might be another copy of.
 *
 * A row with errors is never added. One with possible duplicates is added
 * only when the user includes it. outcome says what became of it: created
 * (record_id is the new record), skipped or failed (message says why).
 */
class ImportRow extends Model
{
    public const CREATED = 'created';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'import_id',
        'line',
        'data',
        'errors',
        'duplicates',
        'include',
        'outcome',
        'record_id',
        'message',
    ];

    protected $casts = [
        'line'       => 'integer',
        'data'       => 'array',
        'errors'     => 'array',
        'duplicates' => 'array',
        'include'    => 'boolean',
        'record_id'  => 'integer',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
