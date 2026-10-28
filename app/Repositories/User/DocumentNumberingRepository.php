<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Models\User\DocumentNumbering;
use App\Models\User\Workspace;

/** The document_numbering table: a row per business and document type whose defaults were changed. */
class DocumentNumberingRepository
{
    public function findFor(Workspace $workspace, DocumentType $type): ?DocumentNumbering
    {
        return $workspace->documentNumbering()->where('document_type', $type->value)->first();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function save(Workspace $workspace, DocumentType $type, array $data): DocumentNumbering
    {
        return $workspace->documentNumbering()->updateOrCreate(['document_type' => $type->value], $data);
    }
}
