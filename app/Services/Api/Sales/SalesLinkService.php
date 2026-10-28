<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\DocumentLink;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentLinkRepository;
use App\Services\Documents\DocumentLinks;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;

/**
 * Share Link on a sales document's page (Doc 06 section 9, Doc 08 section
 * 8): its secure link - the one it has, or a new one - and revoking it, after
 * which the address opens nothing. Sharing needs a numbered document that is
 * not void or cancelled.
 */
class SalesLinkService
{
    use RefusesConflicts;

    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected DocumentLinkRepository $documentLinkRepository,
        protected DocumentLinks $documentLinks,
    ) {}

    /** @return array{link: DocumentLink, url: string} */
    public function share(Workspace $workspace, DocumentType $type, int $id, User $user): array
    {
        $document = $this->salesDocumentService->find($workspace, $type, $id);

        if (! $this->salesDocumentRules->delivers($document, 'share', $this->salesDocumentService->today($workspace))) {
            $this->refuse('Only a numbered document that is not void or cancelled can be shared. Send or issue it first.');
        }

        $before = $this->documentLinkRepository->activeFor($document);
        $link   = $this->documentLinks->ensure($workspace, $document, $user);

        if (! $before) {
            ActivityLogService::record('created', 'Made a secure link to '.$this->salesDocumentService->describe($document), $document, ['source' => 'api']);
        }

        return ['link' => $link, 'url' => $this->documentLinks->url($link)];
    }

    public function revoke(Workspace $workspace, DocumentType $type, int $id): void
    {
        $document = $this->salesDocumentService->find($workspace, $type, $id);

        abort_unless($this->documentLinkRepository->revokeFor($document) > 0, 404, 'This document has no working link.');

        ActivityLogService::record('updated', 'Revoked the secure link to '.$this->salesDocumentService->describe($document), $document, ['source' => 'api']);
    }
}
