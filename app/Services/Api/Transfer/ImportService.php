<?php

namespace App\Services\Api\Transfer;

use App\Jobs\CheckImportRows;
use App\Jobs\RunImportRows;
use App\Models\User\Import;
use App\Models\User\ImportRow;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Shared\ActivityLogService;
use App\Support\Csv;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Importing contacts or items from CSV (Doc 03 section 15), step by step:
 *
 *   start()   reads the file: its header, its rows, a guess at which column
 *             is which field
 *   map()     takes the user's matching and checks every row in the
 *             background (CheckImportRows): errors, and possible duplicates
 *             of the business's records and of the file's other rows
 *   run()     adds the rows that passed - and the duplicates the user chose
 *             to keep - in the background (RunImportRows), then sums up
 *
 * Nothing is added before run(), and no row with errors ever is.
 */
class ImportService
{
    /** Rows a job checks or adds before handing over to the next. */
    public const CHUNK = 250;

    /** The most columns a file may have. */
    private const MAX_COLUMNS = 100;

    public function __construct(
        protected ContactTransfer $contactTransfer,
        protected ItemTransfer $itemTransfer,
    ) {}

    public function transfer(string $kind): Transfer
    {
        return match ($kind) {
            'contacts' => $this->contactTransfer,
            'items'    => $this->itemTransfer,
        };
    }

    /** One of the business's imports; another business's answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): Import
    {
        return Import::where('workspace_id', $workspace->id)->find($id)
            ?? abort(404, 'Import not found.');
    }

    /**
     * Reads an uploaded CSV of $kind: its header row, how many rows follow
     * (up to 5,000), and which column looks like which field.
     */
    public function start(Workspace $workspace, ?User $user, string $kind, UploadedFile $file): Import
    {
        $path     = $file->store("imports/{$workspace->id}", Import::DISK);
        $absolute = Storage::disk(Import::DISK)->path($path);

        try {
            Csv::normalise($absolute);

            $headers = null;
            $count   = 0;

            foreach (Csv::rows($absolute, Csv::delimiter($absolute)) as [, $cells]) {
                if ($headers === null) {
                    $headers = $cells;

                    continue;
                }

                if (++$count > Import::MAX_ROWS) {
                    break;
                }
            }

            $problem = match (true) {
                $headers === null                    => 'This file is empty.',
                count($headers) > self::MAX_COLUMNS  => 'This file has more than '.self::MAX_COLUMNS.' columns. Keep the ones to import.',
                $count === 0                         => 'This file has a header row but no rows under it.',
                $count > Import::MAX_ROWS            => 'A file can hold up to '.number_format(Import::MAX_ROWS).' rows. Split this one and import each part.',
                default                              => null,
            };

            if ($problem) {
                throw ValidationException::withMessages(['file' => $problem]);
            }
        } catch (\Throwable $e) {
            Storage::disk(Import::DISK)->delete($path);

            throw $e;
        }

        // A column without a name gets one, so it can be matched.
        $headers = array_map(fn (string $header, int $index) => $header === '' ? 'Column '.($index + 1) : Str::limit($header, 100, ''), $headers, array_keys($headers));

        return Import::create([
            'workspace_id' => $workspace->id,
            'user_id'      => $user?->id,
            'kind'         => $kind,
            'status'       => Import::MAPPING,
            'file_path'    => $path,
            'file_name'    => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'headers'      => $headers,
            'mapping'      => $this->guess($this->transfer($kind)->fields($workspace), $headers),
            'row_count'    => $count,
        ]);
    }

    /**
     * Takes the matching - field key => column number, or null for none -
     * and checks the rows again in the background. Every required field needs
     * a column, and a column can fill one field only.
     */
    public function map(Import $import, array $mapping): Import
    {
        $this->ensureNotBusy($import);

        if (in_array($import->status, [Import::DONE, Import::FAILED], true)) {
            $this->refuse('This import is finished. Start a new one to import another file.');
        }

        $fields   = $this->transfer($import->kind)->fields($import->workspace);
        $mapping  = array_intersect_key($mapping, $fields);
        $problems = [];

        foreach ($mapping as $key => $column) {
            if ($column !== null && ! array_key_exists((int) $column, $import->headers)) {
                $problems["mapping.{$key}"] = 'Choose one of the file\'s columns.';
            }
        }

        foreach (array_count_values(array_map('strval', array_filter($mapping, fn ($column) => $column !== null))) as $column => $times) {
            if ($times > 1) {
                $problems['mapping'] = "The column \"{$import->headers[(int) $column]}\" is matched to more than one field.";
            }
        }

        foreach ($fields as $key => $field) {
            if ($field['required'] && ($mapping[$key] ?? null) === null) {
                $problems["mapping.{$key}"] = "Match a column to {$field['label']}: every row needs it.";
            }
        }

        if ($problems) {
            throw ValidationException::withMessages($problems);
        }

        DB::transaction(function () use ($import, $mapping, $fields) {
            $import->rows()->delete();
            $import->update([
                'mapping'   => array_map(fn ($column) => $column === null ? null : (int) $column, array_replace(array_fill_keys(array_keys($fields), null), $mapping)),
                'status'    => Import::CHECKING,
                'processed' => 0,
                'summary'   => null,
            ]);
        });

        CheckImportRows::dispatch($import->id)->afterCommit();

        return $import->refresh();
    }

    /**
     * Checks the next rows from where the check got to - all of a chunk or
     * none, so a job tried again starts it afresh. True while more remain;
     * the last chunk also compares the file's rows with each other and sums
     * the check up.
     */
    public function checkNext(Import $import): bool
    {
        $workspace = $import->workspace;
        $transfer  = $this->transfer($import->kind);
        $absolute  = Storage::disk(Import::DISK)->path($import->file_path);
        $rows      = [];
        $index     = -1;

        foreach (Csv::rows($absolute, Csv::delimiter($absolute)) as [$line, $cells]) {
            // The header, then the rows already checked.
            if ($index++ < $import->processed) {
                continue;
            }

            $values = [];

            foreach ($import->mapping as $key => $column) {
                if ($column !== null) {
                    $values[$key] = $cells[$column] ?? '';
                }
            }

            [$payload, $unreadable] = $transfer->payload($workspace, $values);

            // A cell that cannot be read says so; the form's check says the
            // rest - keyed by field, as the Status column is "status".
            $errors = $unreadable + $transfer->errors($workspace, $payload);

            if (isset($errors['is_active'])) {
                $errors['status'] ??= $errors['is_active'];
                unset($errors['is_active']);
            }

            $rows[] = [
                'import_id'  => $import->id,
                'line'       => $line,
                'data'       => json_encode($payload),
                'errors'     => $errors ? json_encode($errors) : null,
                'duplicates' => ! $errors && ($duplicates = $transfer->duplicatesOf($workspace, $payload)) ? json_encode($duplicates) : null,
                'include'    => false,
            ];

            if (count($rows) === self::CHUNK) {
                break;
            }
        }

        DB::transaction(function () use ($import, $rows) {
            if ($rows) {
                ImportRow::insert($rows);
            }

            $import->update(['processed' => $import->processed + count($rows)]);
        });

        if ($rows && $import->processed < $import->row_count) {
            return true;
        }

        $this->compareRows($import, $transfer);

        return false;
    }

    /**
     * Adds the rows: those without errors or possible duplicates, and the
     * duplicates whose lines are in $include. Runs in the background.
     */
    public function run(Import $import, array $include): Import
    {
        $this->ensureNotBusy($import);

        if (in_array($import->status, [Import::DONE, Import::FAILED], true)) {
            $this->refuse('This import is finished. Start a new one to import another file.');
        }

        if ($import->status !== Import::READY) {
            $this->refuse('Check the file before adding its rows.');
        }

        DB::transaction(function () use ($import, $include) {
            $import->rows()->update(['include' => false]);
            $import->rows()->whereNotNull('duplicates')->whereIn('line', array_map('intval', $include))->update(['include' => true]);
            $import->update(['status' => Import::IMPORTING, 'processed' => 0, 'result' => null]);
        });

        RunImportRows::dispatch($import->id)->afterCommit();

        return $import->refresh();
    }

    /**
     * Adds the next rows not yet done, each in a transaction of its own with
     * its outcome - so a job tried again carries on where it stopped. True
     * while more remain; the last also sums up, logs once and lets the file
     * go.
     */
    public function runNext(Import $import): bool
    {
        $workspace = $import->workspace;
        $transfer  = $this->transfer($import->kind);
        $rows      = $import->rows()->whereNull('outcome')->limit(self::CHUNK)->get();

        foreach ($rows as $row) {
            if ($row->errors || ($row->duplicates && ! $row->include)) {
                $row->update(['outcome' => ImportRow::SKIPPED, 'message' => $row->errors ? 'It has errors.' : 'It may be a duplicate.']);

                continue;
            }

            try {
                DB::transaction(function () use ($row, $transfer, $workspace) {
                    $record = $transfer->create($workspace, $row->data);

                    $row->update(['outcome' => ImportRow::CREATED, 'record_id' => $record->getKey()]);
                });
            } catch (\Throwable $e) {
                report($e);
                $row->update(['outcome' => ImportRow::FAILED, 'message' => 'It could not be added - most likely something in it changed since the check. Add it from the form.']);
            }
        }

        $import->update(['processed' => $import->processed + $rows->count()]);

        if ($rows->count() === self::CHUNK && $import->rows()->whereNull('outcome')->exists()) {
            return true;
        }

        $this->finish($import, $transfer);

        return false;
    }

    /** @return LengthAwarePaginator<ImportRow> */
    public function rows(Import $import, string $show, int $perPage): LengthAwarePaginator
    {
        return $import->rows()
            ->when($show === 'ready', fn ($query) => $query->whereNull('errors')->whereNull('duplicates'))
            ->when($show === 'duplicates', fn ($query) => $query->whereNull('errors')->whereNotNull('duplicates'))
            ->when($show === 'errors', fn ($query) => $query->whereNotNull('errors'))
            ->when(in_array($show, [ImportRow::CREATED, ImportRow::SKIPPED, ImportRow::FAILED], true), fn ($query) => $query->where('outcome', $show))
            ->paginate($perPage);
    }

    /** The first rows of the file, cell by cell, for matching its columns by what they hold. */
    public function samples(Import $import, int $count = 3): array
    {
        $absolute = Storage::disk(Import::DISK)->path($import->file_path);

        if (! is_file($absolute)) {
            return [];
        }

        $samples = [];

        foreach (Csv::rows($absolute, Csv::delimiter($absolute)) as $index => [, $cells]) {
            if ($index === 0) {
                continue;
            }

            $samples[] = array_map(fn (string $cell) => Str::limit($cell, 60), $cells);

            if (count($samples) === $count) {
                break;
            }
        }

        return $samples;
    }

    /** Drops an import that is not being worked on - its rows and its file. */
    public function discard(Import $import): void
    {
        $this->ensureNotBusy($import);

        $import->delete();
    }

    /**
     * Within the file: a row whose unique key (a SKU, a barcode) an earlier
     * row has is an error; one an earlier row looks like is a possible
     * duplicate of it. Then the counts the check screen shows.
     */
    private function compareRows(Import $import, Transfer $transfer): void
    {
        $fields  = $transfer->fields($import->workspace);
        $uniques = [];
        $looks   = [];

        foreach ($import->rows()->get() as $row) {
            if ($row->errors) {
                continue;
            }

            $keys       = $transfer->keys($row->data);
            $errors     = [];
            $duplicates = $row->duplicates ?? [];

            foreach ($keys['unique'] as $field => $key) {
                if (isset($uniques[$key])) {
                    $errors[$field] = "Row {$uniques[$key]} of this file has the same ".($fields[$field]['label'] ?? $field).'.';
                } else {
                    $uniques[$key] = $row->line;
                }
            }

            $earlier = [];

            foreach ($keys['duplicates'] as $key) {
                $matched = str_replace('tax', 'tax_id', Str::before($key, ':'));

                if (isset($looks[$key])) {
                    $earlier[$looks[$key]][] = $matched;
                } else {
                    $looks[$key] = $row->line;
                }
            }

            foreach ($earlier as $line => $matchedOn) {
                $duplicates[] = ['row' => $line, 'matched_on' => array_values(array_unique($matchedOn))];
            }

            if ($errors) {
                $row->update(['errors' => $errors, 'duplicates' => null]);
            } elseif ($duplicates !== ($row->duplicates ?? [])) {
                $row->update(['duplicates' => $duplicates]);
            }
        }

        $import->update([
            'status'  => Import::READY,
            'summary' => [
                'rows'       => $import->row_count,
                'ready'      => $import->rows()->whereNull('errors')->whereNull('duplicates')->count(),
                'duplicates' => $import->rows()->whereNull('errors')->whereNotNull('duplicates')->count(),
                'errors'     => $import->rows()->whereNotNull('errors')->count(),
            ],
        ]);
    }

    /** The import's result, its one log entry, and the end of its file. */
    private function finish(Import $import, Transfer $transfer): void
    {
        $count = fn (string $outcome) => $import->rows()->where('outcome', $outcome)->count();

        $import->update([
            'status' => Import::DONE,
            'result' => [
                'created' => $count(ImportRow::CREATED),
                'skipped' => $count(ImportRow::SKIPPED),
                'failed'  => $count(ImportRow::FAILED),
            ],
        ]);

        $import->forgetFile();

        $created = $import->result['created'];

        ActivityLogService::record(
            'created',
            "Imported {$created} ".Str::plural(Str::singular($transfer->plural()), $created).' from "'.Str::limit($import->file_name, 80).'"',
            $import->workspace,
            ['source' => 'api', 'import_id' => $import->id, ...$import->result],
            User::find($import->user_id),
        );
    }

    /** Field key => column number, for every field whose label - or a word for it - heads a column. */
    private function guess(array $fields, array $headers): array
    {
        $fold    = fn (string $text) => (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($text));
        $columns = [];

        foreach ($headers as $index => $header) {
            $columns[$fold($header)] ??= $index;
        }

        $mapping = [];
        $taken   = [];

        // Labels first, so "Billing City" takes its column before "city", an
        // alias of the same field, could lead another field to it.
        foreach ([true, false] as $byLabel) {
            foreach ($fields as $key => $field) {
                if (isset($mapping[$key])) {
                    continue;
                }

                foreach ($byLabel ? [$field['label']] : $field['aliases'] as $name) {
                    $column = $columns[$fold($name)] ?? null;

                    if ($column !== null && ! isset($taken[$column])) {
                        $mapping[$key] = $taken[$column] = $column;

                        break;
                    }
                }
            }
        }

        return array_replace(array_fill_keys(array_keys($fields), null), $mapping);
    }

    private function ensureNotBusy(Import $import): void
    {
        if ($import->isBusy()) {
            $this->refuse('This import is still working. Wait for it to finish.');
        }
    }

    private function refuse(string $message): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], 409));
    }
}
