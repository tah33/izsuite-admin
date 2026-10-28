<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\User\DocumentTemplate;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentTemplateRepository;

/**
 * Which template a document is shown with (Doc 11 section 20): the one it
 * kept when it was numbered or recorded, so later changes never restyle what
 * was issued - or, for a draft or a document from before templates, the
 * business's template as it is now.
 */
class DocumentTemplates
{
    public function __construct(
        protected DocumentTemplateRepository $documentTemplateRepository,
    ) {}

    /** The business's template for $type, or the defaults. */
    public function current(Workspace $workspace, DocumentType $type): DocumentTemplate
    {
        return $this->documentTemplateRepository->findFor($workspace, $type) ?? DocumentTemplate::defaultsFor($type);
    }

    /** What a document keeps when it is numbered: the template's settings and its signature image. */
    public function snapshot(Workspace $workspace, DocumentType $type): array
    {
        $template = $this->current($workspace, $type);

        return $template->settings($this->documentTemplateRepository->signatureOf($template)?->id);
    }

    /**
     * What a document is shown with: its own snapshot, or the current
     * template - over the defaults, so a setting added since still has a value.
     * The colour goes into a stylesheet, so only a hex colour gets there.
     */
    public function settingsFor(Workspace $workspace, DocumentType $type, ?array $snapshot): array
    {
        $settings = [...DocumentTemplate::defaultsFor($type)->settings(null), ...($snapshot ?: $this->snapshot($workspace, $type))];

        if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings['color'])) {
            $settings['color'] = DocumentTemplate::defaultsFor($type)->color;
        }

        return $settings;
    }
}
