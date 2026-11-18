<?php

namespace App\Services\Documents;

use App\Enums\Edition;
use App\Enums\SalesDocumentStatus;
use App\Models\User\DocumentLink;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Services\User\Apps\WorkspaceApps;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Secure links (Doc 06 section 9, Doc 08 section 8): an address that opens
 * one sales document - to read it and download its PDF - without signing in.
 * The token is 48 random letters and digits, so it cannot be guessed. A link
 * works until it is revoked, or its document is voided or cancelled - or, on
 * the full Sales app, it expires: a new one lasts the days Settings > Sales
 * gives it, none for never (Doc 16 Phase G2).
 */
class DocumentLinks
{
    public function __construct(
        protected DocumentLinkRepository $documentLinkRepository,
        protected SalesSettingRepository $salesSettingRepository,
    ) {}

    /** $document's working link: the one it has, or a new one. */
    public function ensure(Workspace $workspace, SalesDocument $document, User $user): DocumentLink
    {
        return $this->documentLinkRepository->activeFor($document)
            ?? $this->documentLinkRepository->create($workspace, $document, ['token' => $this->token(), 'created_by' => $user->id, 'expires_at' => $this->expiry($workspace)]);
    }

    /** Where the customer opens it. */
    public function url(DocumentLink $link): string
    {
        return route('documents.public', $link->token);
    }

    /** Whether $link still opens its document. Expects linkable loaded. */
    public function works(DocumentLink $link): bool
    {
        $document = $link->linkable;

        return ! $link->revoked_at && ! $link->expires_at?->isPast() && $document instanceof SalesDocument
            && ! in_array($document->status, [SalesDocumentStatus::Draft, SalesDocumentStatus::Void, SalesDocumentStatus::Cancelled], true);
    }

    /** When a link made now stops working: Settings > Sales's days on, the full Sales app - null for never. */
    private function expiry(Workspace $workspace): ?CarbonImmutable
    {
        $days = WorkspaceApps::for($workspace)->has('sales', Edition::Full) ? $this->salesSettingRepository->forWorkspace($workspace)->link_expiry_days : null;

        return $days ? CarbonImmutable::now()->addDays($days) : null;
    }

    private function token(): string
    {
        do {
            $token = Str::random(48);
        } while ($this->documentLinkRepository->tokenTaken($token));

        return $token;
    }
}
