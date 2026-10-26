<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One CSV import of contacts or items (Doc 03 section 15), and where it has
 * got to:
 *
 *   mapping    the file is read; its columns wait to be matched to fields
 *   checking   every row is being checked - errors, possible duplicates
 *   ready      the check is shown; the user picks what to add
 *   importing  the rows are being added
 *   done       added; result holds how many were added, skipped and failed
 *   failed     something went wrong; error says what
 *
 * processed counts the rows checked or added so far, for a progress bar.
 * The file waits on the private disk until the import is done or dropped -
 * or a week has passed (see prunable()).
 */
class Import extends Model
{
    use Prunable;

    public const KINDS = ['contacts', 'items'];

    public const MAPPING = 'mapping';

    public const CHECKING = 'checking';

    public const READY = 'ready';

    public const IMPORTING = 'importing';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** The disk the uploaded file waits on - never public, never counted toward storage. */
    public const DISK = 'local';

    /** The most rows one file may hold. */
    public const MAX_ROWS = 5000;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'kind',
        'status',
        'file_path',
        'file_name',
        'headers',
        'mapping',
        'row_count',
        'processed',
        'summary',
        'result',
        'error',
    ];

    protected $casts = [
        'headers'   => 'array',
        'mapping'   => 'array',
        'summary'   => 'array',
        'result'    => 'array',
        'row_count' => 'integer',
        'processed' => 'integer',
    ];

    protected static function booted(): void
    {
        // Its rows go by the database; its file by hand.
        static::deleting(fn (self $import) => $import->forgetFile());
    }

    /** Whether a job is working on it - it cannot be changed or dropped meanwhile. */
    public function isBusy(): bool
    {
        return in_array($this->status, [self::CHECKING, self::IMPORTING], true);
    }

    /** The uploaded file, gone once it is no longer needed. */
    public function forgetFile(): void
    {
        Storage::disk(self::DISK)->delete($this->file_path);
    }

    /** Imports left a week ago, finished or not - run daily (routes/console.php). */
    public function prunable(): Builder
    {
        return static::where('updated_at', '<', now()->subWeek());
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('line');
    }
}
