<?php

namespace App\Services\Documents;

use App\Models\User\DocumentLink;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\SalesDocumentRepository;

/**
 * What a secure link opens for someone who is not signed in (Doc 06 section
 * 9): the document as its PDF shows it, on a page of its own, and its PDF.
 * Each page view is counted. A link that no longer works opens nothing - the
 * same for one revoked and one that never was.
 */
class PublicDocuments
{
    public function __construct(
        protected DocumentLinkRepository $documentLinkRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected DocumentLinks $documentLinks,
        protected DocumentRenderer $documentRenderer,
        protected DocumentPdf $documentPdf,
    ) {}

    /** The page, with a button to $pdfUrl - null when the link does not work. */
    public function page(string $token, string $pdfUrl): ?RenderedDocument
    {
        $link = $this->working($token);

        if (! $link) {
            return null;
        }

        $this->documentLinkRepository->recordView($link);

        return $this->documentRenderer->sales($link->workspace, $this->salesDocumentRepository->loadForPdf($link->linkable), $pdfUrl);
    }

    /** @return array{0: string, 1: string}|null the file name and the PDF */
    public function pdf(string $token): ?array
    {
        $link = $this->working($token);

        return $link ? $this->documentPdf->sales($link->workspace, $this->salesDocumentRepository->loadForPdf($link->linkable)) : null;
    }

    private function working(string $token): ?DocumentLink
    {
        $link = $this->documentLinkRepository->findByToken($token);

        return $link && $this->documentLinks->works($link) ? $link : null;
    }
}
