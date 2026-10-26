<?php

namespace App\Repositories\User;

use App\Models\User\Import;
use App\Models\User\ImportRow;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * A business's CSV imports and the rows read from each file.
 */
class ImportRepository
{
    public function findFor(Workspace $workspace, int $id): ?Import
    {
        return Import::where('workspace_id', $workspace->id)->find($id);
    }

    /** Any business's import - for the jobs that work through one. */
    public function find(int $id): ?Import
    {
        return Import::find($id);
    }

    public function create(array $data): Import
    {
        return Import::create($data);
    }

    public function update(Import $import, array $data): Import
    {
        $import->update($data);

        return $import;
    }

    /** Sets $data on import $id without loading it - a failed job's last word. */
    public function updateById(int $id, array $data): void
    {
        Import::whereKey($id)->update($data);
    }

    public function refresh(Import $import): Import
    {
        return $import->refresh();
    }

    public function delete(Import $import): void
    {
        $import->delete();
    }

    public function deleteRows(Import $import): void
    {
        $import->rows()->delete();
    }

    /** Rows as they go into the table - data, errors and duplicates already JSON. */
    public function insertRows(array $rows): void
    {
        ImportRow::insert($rows);
    }

    public function updateRow(ImportRow $row, array $data): void
    {
        $row->update($data);
    }

    public function rows(Import $import): Collection
    {
        return $import->rows()->get();
    }

    /** The next $limit rows with no outcome yet. */
    public function rowsToAdd(Import $import, int $limit): Collection
    {
        return $import->rows()->whereNull('outcome')->limit($limit)->get();
    }

    public function hasRowsToAdd(Import $import): bool
    {
        return $import->rows()->whereNull('outcome')->exists();
    }

    /** Includes the possible duplicates on $lines, and no other row. */
    public function includeDuplicates(Import $import, array $lines): void
    {
        $import->rows()->update(['include' => false]);
        $import->rows()->whereNotNull('duplicates')->whereIn('line', $lines)->update(['include' => true]);
    }

    /**
     * A page of rows: every one, or those $show names - ready (no errors, no
     * possible duplicates), duplicates, errors, or an outcome.
     *
     * @return LengthAwarePaginator<ImportRow>
     */
    public function paginateRows(Import $import, string $show, int $perPage): LengthAwarePaginator
    {
        return $import->rows()
            ->when($show === 'ready', fn ($query) => $query->whereNull('errors')->whereNull('duplicates'))
            ->when($show === 'duplicates', fn ($query) => $query->whereNull('errors')->whereNotNull('duplicates'))
            ->when($show === 'errors', fn ($query) => $query->whereNotNull('errors'))
            ->when(in_array($show, [ImportRow::CREATED, ImportRow::SKIPPED, ImportRow::FAILED], true), fn ($query) => $query->where('outcome', $show))
            ->paginate($perPage);
    }

    /** How many rows are ready, how many possible duplicates, how many have errors. */
    public function checkCounts(Import $import): array
    {
        return [
            'ready'      => $import->rows()->whereNull('errors')->whereNull('duplicates')->count(),
            'duplicates' => $import->rows()->whereNull('errors')->whereNotNull('duplicates')->count(),
            'errors'     => $import->rows()->whereNotNull('errors')->count(),
        ];
    }

    public function countWithOutcome(Import $import, string $outcome): int
    {
        return $import->rows()->where('outcome', $outcome)->count();
    }
}
