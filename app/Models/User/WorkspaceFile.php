<?php

namespace App\Models\User;

use App\Enums\FileCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One file a business keeps: which record it belongs to (its attachable) and
 * in which collection, the disk and path it is stored at, and what it was
 * when uploaded - name, type and size. The size counts toward the business's
 * storage. Stored and removed through FileStorage, never directly.
 */
class WorkspaceFile extends Model
{
    public const PUBLIC = 'public';

    public const PRIVATE = 'private';

    protected $fillable = [
        'workspace_id',
        'attachable_type',
        'attachable_id',
        'collection',
        'disk',
        'path',
        'thumbnail_path',
        'visibility',
        'name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'collection' => FileCollection::class,
        'size'       => 'integer',
    ];

    public function isPublic(): bool
    {
        return $this->visibility === self::PUBLIC;
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The record it belongs to: a contact, an item, the workspace (its logo). */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
