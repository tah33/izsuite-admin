<?php

namespace App\Console\Commands;

use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Files\FileStorage;
use Illuminate\Console\Command;

/**
 * Moves businesses' files to one storage - this server or DigitalOcean Spaces -
 * after FILES_DISK changes, so every file lives in one place again. Files
 * work from where they are meanwhile, so there is no rush.
 *
 * Each copy is checked before its record points at it and the original goes
 * only after; a file that fails stays where it was, and running the command
 * again picks it up.
 */
class MoveFiles extends Command
{
    protected $signature = 'files:move
                            {--to= : Where to move them: local or spaces}
                            {--workspace= : Only this business\'s files}
                            {--dry-run : Count what would move, without moving it}';

    protected $description = "Move businesses' files between this server and DigitalOcean Spaces";

    public function handle(FileStorage $fileStorage, WorkspaceFileRepository $files): int
    {
        $to = (string) $this->option('to');

        if (! array_key_exists($to, config('files.disks'))) {
            $this->error('Say where to move the files: --to=local or --to=spaces.');

            return self::INVALID;
        }

        $moved   = 0;
        $already = 0;
        $failed  = 0;

        foreach ($files->lazyAll($this->option('workspace')) as $file) {
            if ($fileStorage->diskFor($file->isPublic(), $to) === $file->disk) {
                $already++;

                continue;
            }

            if ($this->option('dry-run')) {
                $moved++;

                continue;
            }

            try {
                $fileStorage->move($file, $to);
                $moved++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $this->warn("File {$file->id} ({$file->path}) stayed where it was: {$e->getMessage()}");
            }
        }

        $this->info(($this->option('dry-run') ? 'Would move' : 'Moved')." {$moved} file(s) to {$to}; {$already} already there; {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
