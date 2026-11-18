<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Services\User\Apps\WorkspaceApps;

/**
 * The kinds of document a business has settings for - numbering, templates -
 * which are those of the apps it has on - a sales order's with the full Sales
 * app (Doc 16 Phase B). Every kind has a template (Doc 11
 * section 20).
 */
class DocumentTypes
{
    /**
     * @param  bool  $templated  only those with a template of their own - a delivery note has its order's (Doc 16 Phase C)
     * @return list<DocumentType> in DocumentType's order
     */
    public function for(Workspace $workspace, bool $templated = false): array
    {
        $apps = WorkspaceApps::for($workspace);

        return array_values(array_filter(DocumentType::cases(), fn (DocumentType $type) => $apps->has($type->family(), $type->edition()) && (! $templated || $type->hasTemplate())));
    }

    /** The document type named in the address - 404 for one that is not, or whose app is off. */
    public function named(Workspace $workspace, string $value, bool $templated = false): DocumentType
    {
        $type = DocumentType::tryFrom($value);

        abort_unless($type && in_array($type, $this->for($workspace, $templated), true), 404, 'There is no such kind of document.');

        return $type;
    }
}
