<?php

namespace App\Services\Files;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Support\Bytes;
use Illuminate\Validation\ValidationException;

/**
 * How much file storage a business uses, against what its package gives it
 * (Doc 13 section 13.14): the sizes of its files as uploaded, against the
 * package's storage limit, set in the admin. A package without one has no
 * limit.
 *
 * Over the limit, the files already stored stay viewable and downloadable;
 * only new uploads are refused, until space is freed or the package changes.
 */
class StorageUsage
{
    public function usedBytes(Workspace $workspace): int
    {
        return (int) WorkspaceFile::where('workspace_id', $workspace->id)->sum('size');
    }

    /** Null: no limit. */
    public function limitBytes(Workspace $workspace): ?int
    {
        $megabytes = $workspace->package?->storage_limit_mb;

        return $megabytes === null ? null : $megabytes * 1024 * 1024;
    }

    /**
     * What Settings shows: used and limit in bytes, the share used, and a
     * state - "ok", "warning" once the warning share is reached, "full" once
     * nothing more fits.
     */
    public function summary(Workspace $workspace): array
    {
        $used  = $this->usedBytes($workspace);
        $limit = $this->limitBytes($workspace);

        return [
            'used_bytes'  => $used,
            'limit_bytes' => $limit,
            'percent'     => $limit ? round($used / $limit * 100, 1) : null,
            'state'       => match (true) {
                $limit === null                                                => 'ok',
                $used >= $limit                                                => 'full',
                $used >= $limit * config('files.warning_percent', 80) / 100   => 'warning',
                default                                                        => 'ok',
            },
        ];
    }

    /**
     * Refuses $bytes more that the business has no room for, with a 422 on
     * $field that says how much is used and left. $freed is what the upload
     * replaces (an item's previous image), which is let go of once it is in.
     */
    public function ensureRoomFor(Workspace $workspace, int $bytes, int $freed, string $field): void
    {
        $limit = $this->limitBytes($workspace);

        if ($limit === null) {
            return;
        }

        $used = max(0, $this->usedBytes($workspace) - $freed);

        if ($used + $bytes <= $limit) {
            return;
        }

        $message = $used >= $limit
            ? 'Your storage is full: '.Bytes::format($used).' of '.Bytes::format($limit).' used. Delete files you no longer need, or move to a package with more storage.'
            : 'This needs '.Bytes::format($bytes).', but only '.Bytes::format($limit - $used).' of your '.Bytes::format($limit).' storage is left.';

        throw ValidationException::withMessages([$field => $message]);
    }
}
