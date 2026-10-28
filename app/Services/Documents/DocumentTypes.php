<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Services\User\Apps\WorkspaceApps;

/**
 * The kinds of document a business has settings for - numbering, templates -
 * which are those of the apps it has on.
 */
class DocumentTypes
{
    /** @return list<DocumentType> in DocumentType's order */
    public function for(Workspace $workspace): array
    {
        $apps = WorkspaceApps::for($workspace);

        return array_values(array_filter(DocumentType::cases(), fn (DocumentType $type) => $apps->has($type->family(), Edition::Lite)));
    }

    /** The document type named in the address - 404 for one that is not, or whose app is off. */
    public function named(Workspace $workspace, string $value): DocumentType
    {
        $type = DocumentType::tryFrom($value);

        abort_unless($type && in_array($type, $this->for($workspace), true), 404, 'There is no such kind of document.');

        return $type;
    }
}
