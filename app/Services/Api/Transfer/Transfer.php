<?php

namespace App\Services\Api\Transfer;

use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What one kind of record - contacts, items - brings to CSV import and export
 * (Doc 03 section 15, Doc 04 section 17): the columns, how a row becomes what
 * the API takes, how it is checked, what counts as a possible duplicate, and
 * how it is added.
 *
 * An export's headers are the import's labels, so an exported file imports
 * again with every column matched.
 */
interface Transfer
{
    /** The records, as people name them: "contacts". */
    public function plural(): string;

    /**
     * The fields an import can fill, in the template's order, by key: label,
     * whether a row needs it, other headers that mean it, and what a cell
     * may hold.
     *
     * @return array<string, array{label: string, required: bool, aliases: list<string>, hint: ?string}>
     */
    public function fields(Workspace $workspace): array;

    /** The template's example row, by field key. */
    public function example(): array;

    /** The export's columns: header => what a record shows there. */
    public function exportColumns(Workspace $workspace): array;

    /** The records an export holds: those the list shows with $filters, in its order. */
    public function exportQuery(Workspace $workspace, array $filters): Builder;

    /**
     * A row's cells, by field key, as the API takes them, and the cells that
     * cannot be read, by field key.
     *
     * @return array{0: array, 1: array<string, string>}
     */
    public function payload(Workspace $workspace, array $cells): array;

    /** What is wrong with a payload, by field key - the same checks as the API's form. */
    public function errors(Workspace $workspace, array $payload): array;

    /**
     * Keys that say two rows of one file are possible duplicates of each
     * other ("email:rahim@uddin.test"), and keys that may only appear once
     * ("sku:CHR-001").
     *
     * @return array{duplicates: list<string>, unique: array<string, string>}
     */
    public function keys(array $payload): array;

    /**
     * Records the business already has that a payload might be another copy
     * of: up to five, each with its id, number, name and what matched.
     */
    public function duplicatesOf(Workspace $workspace, array $payload): array;

    /** Adds a record from a checked payload, without its own activity log entry - the import logs once. */
    public function create(Workspace $workspace, array $payload): Model;
}
