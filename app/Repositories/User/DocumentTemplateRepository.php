<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\FileCollection;
use App\Models\User\DocumentTemplate;
use App\Models\User\WorkspaceFile;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Document templates belong to one business, one row per document type that
 * was changed from the defaults. Every lookup is scoped to the business.
 */
class DocumentTemplateRepository
{
    /** The business's own templates, by type value. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->documentTemplates()->get()->keyBy(fn (DocumentTemplate $template) => $template->document_type->value);
    }

    public function findFor(Workspace $workspace, DocumentType $type): ?DocumentTemplate
    {
        return $workspace->documentTemplates()->where('document_type', $type->value)->first();
    }

    /** $type's template made with $data, or changed to it. */
    public function save(Workspace $workspace, DocumentType $type, array $data): DocumentTemplate
    {
        return $workspace->documentTemplates()->updateOrCreate(['document_type' => $type->value], $data);
    }

    /** The template's signature or stamp image, if it has one. */
    public function signatureOf(DocumentTemplate $template): ?WorkspaceFile
    {
        return $template->exists ? $template->filesIn(FileCollection::Signature)->first() : null;
    }
}
