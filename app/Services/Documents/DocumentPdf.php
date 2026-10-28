<?php

namespace App\Services\Documents;

use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;

/**
 * A document's PDF (Doc 06 section 15): drawn up by DocumentRenderer, made by
 * Gotenberg - A4 or Letter, every page with its number and "Powered by
 * izSuite". Made when asked for, from what the document kept, so the same
 * document always comes out the same.
 */
class DocumentPdf
{
    public function __construct(
        protected DocumentRenderer $documentRenderer,
        protected GotenbergClient $gotenbergClient,
    ) {}

    /** @return array{0: string, 1: string} its file name and its bytes */
    public function sales(Workspace $workspace, SalesDocument $document): array
    {
        return $this->made($this->documentRenderer->sales($workspace, $document));
    }

    /** @return array{0: string, 1: string} */
    public function receipt(Workspace $workspace, SalesPayment $payment): array
    {
        return $this->made($this->documentRenderer->receipt($workspace, $payment));
    }

    private function made(RenderedDocument $rendered): array
    {
        return [$rendered->filename, $this->gotenbergClient->pdf($rendered->html, $rendered->paper, $rendered->footer)];
    }
}
