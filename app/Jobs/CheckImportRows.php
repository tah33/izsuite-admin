<?php

namespace App\Jobs;

use App\Models\User\Import;
use App\Services\Api\Transfer\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks the next rows of an import (see ImportService::checkNext()) and hands
 * over to a job of its own for the rest, so a file of thousands never runs
 * into the worker's time limit. Gives up quietly on an import that has moved
 * on or gone.
 */
class CheckImportRows implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
    ) {}

    public function handle(ImportService $imports): void
    {
        $import = $imports->findById($this->importId);

        if ($import?->status !== Import::CHECKING) {
            return;
        }

        if ($imports->checkNext($import)) {
            self::dispatch($this->importId);
        }
    }

    public function failed(?\Throwable $e): void
    {
        app(ImportService::class)->markFailed($this->importId, 'The file could not be checked. Start the import again, and if it happens again, contact support.');
    }
}
