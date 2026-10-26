<?php

namespace App\Jobs;

use App\Models\User\Import;
use App\Services\Api\Transfer\ImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Adds the next rows of an import (see ImportService::runNext()) and hands
 * over to a job of its own for the rest. Each row is added with its outcome,
 * so a job tried again carries on where the last one stopped.
 */
class RunImportRows implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $importId,
    ) {}

    public function handle(ImportService $imports): void
    {
        $import = Import::find($this->importId);

        if ($import?->status !== Import::IMPORTING) {
            return;
        }

        if ($imports->runNext($import)) {
            self::dispatch($this->importId);
        }
    }

    public function failed(?\Throwable $e): void
    {
        Import::whereKey($this->importId)->update([
            'status' => Import::FAILED,
            'error'  => 'The import stopped part-way. The rows already added are kept; check the list before importing the file again.',
        ]);
    }
}
