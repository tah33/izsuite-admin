<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Removes stored copies whose rows are gone - a deleted attachment, a replaced
 * image, everything of a deleted business. Queued, so a business with many
 * files on DigitalOcean Spaces is not deleted inside one request; dispatched
 * after the deletion commits, so a rolled-back one never loses its files.
 *
 * A copy already gone is fine. One that cannot be removed is reported and the
 * rest are still tried.
 */
class DeleteStoredFiles implements ShouldQueue
{
    use Queueable;

    /** @param  list<array{0: string, 1: string}>  $objects  [disk, path] pairs */
    public function __construct(
        public array $objects,
    ) {}

    public function handle(): void
    {
        foreach ($this->objects as [$disk, $path]) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
