<?php

namespace App\Services\Api\Documents;

use App\Enums\DocumentType;
use App\Models\User\DocumentNumbering;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentNumberingRepository;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTypes;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;

/**
 * Settings > Documents > Numbering (Doc 11 section 21): how each kind of
 * document is numbered, for the apps the business has on. A change applies to
 * documents numbered from then on; numbers already given stay as they are.
 *
 * Each row comes with the next number it would give today, worked out the way
 * DocumentNumbers gives them.
 */
class NumberingService
{
    private const FIELDS = ['prefix', 'starting_number', 'number_length', 'period'];

    public function __construct(
        protected DocumentNumberingRepository $documentNumberingRepository,
        protected DocumentNumbers $documentNumbers,
        protected BusinessCalendar $businessCalendar,
        protected DocumentTypes $documentTypes,
    ) {}

    /** @return list<array{scheme: DocumentNumbering, next_number: string}> */
    public function list(Workspace $workspace): array
    {
        return array_map(fn (DocumentType $type) => $this->row($workspace, $this->documentNumbers->scheme($workspace, $type)), $this->documentTypes->for($workspace));
    }

    /** The document type named in the address - 404 for one that is not, or whose app is off. */
    public function type(Workspace $workspace, string $value): DocumentType
    {
        return $this->documentTypes->named($workspace, $value);
    }

    /** $data is validated (see SaveNumberingRequest). */
    public function save(Workspace $workspace, DocumentType $type, array $data): array
    {
        $before = $this->documentNumbers->scheme($workspace, $type);
        $scheme = $this->documentNumberingRepository->save($workspace, $type, $data);

        ActivityLogService::record('updated', "Changed how {$type->plural()} are numbered", $workspace, [
            'source' => 'api',
            'fields' => array_values(array_filter(self::FIELDS, fn (string $field) => $this->value($before, $field) !== $this->value($scheme, $field))),
        ]);

        return $this->row($workspace, $scheme);
    }

    /** The next number $data would give today - nothing is saved or taken. */
    public function preview(Workspace $workspace, DocumentType $type, array $data): string
    {
        $scheme = $this->documentNumbers->scheme($workspace, $type)->fill($data);

        return $this->documentNumbers->preview($workspace, $scheme, $this->businessCalendar->today($workspace));
    }

    private function row(Workspace $workspace, DocumentNumbering $scheme): array
    {
        return [
            'scheme'      => $scheme,
            'next_number' => $this->documentNumbers->preview($workspace, $scheme, $this->businessCalendar->today($workspace)),
        ];
    }

    private function value(DocumentNumbering $scheme, string $field): string
    {
        $value = $scheme->{$field};

        return (string) ($value instanceof \BackedEnum ? $value->value : $value);
    }
}
