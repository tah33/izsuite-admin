<?php

namespace App\Services\Api\Transfer;

use App\Models\User\Workspace;
use App\Services\Shared\ActivityLogService;
use App\Support\Csv;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporting contacts or items to CSV (Doc 03 section 15, Doc 04 section 17):
 * the records the list shows with its filters and search, in its order, with
 * the columns of an import - so the file can be imported again. Written as it
 * is read, so an export of thousands is never held whole. Every export is
 * logged, with its filters.
 */
class ExportService
{
    public function __construct(
        protected ImportService $importService,
    ) {}

    public function download(Workspace $workspace, string $kind, array $filters): StreamedResponse
    {
        $transfer = $this->importService->transfer($kind);
        $columns  = $transfer->exportColumns($workspace);
        $query    = $transfer->exportQuery($workspace, $filters);

        $count = $query->count();

        ActivityLogService::record('exported', "Exported {$count} ".Str::plural(Str::singular($transfer->plural()), $count), $workspace, [
            'source'  => 'api',
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]);

        $rows = (function () use ($query, $columns) {
            foreach ($query->lazy(500) as $record) {
                yield array_map(fn (callable $value) => $value($record), array_values($columns));
            }
        })();

        return Csv::download("{$transfer->plural()}-".now()->format('Y-m-d').'.csv', array_keys($columns), $rows);
    }

    /** An empty file to fill in: the import's columns, and one example row. */
    public function template(Workspace $workspace, string $kind): StreamedResponse
    {
        $transfer = $this->importService->transfer($kind);
        $fields   = $transfer->fields($workspace);
        $example  = $transfer->example();

        return Csv::download(
            "{$transfer->plural()}-import-template.csv",
            array_column($fields, 'label'),
            [array_map(fn (string $key) => $example[$key] ?? '', array_keys($fields))],
        );
    }
}
