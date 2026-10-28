<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDocumentRevision;
use App\Models\User\Workspace;
use App\Repositories\User\SalesDocumentRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * The earlier versions of a sent quotation or proforma (Doc 08 section 12):
 * which there are, and each one whole, read-only.
 */
class SalesRevisionService
{
    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesDocumentService $salesDocumentService,
    ) {}

    /** Its earlier versions, oldest first, each with who changed it and when. */
    public function list(Workspace $workspace, DocumentType $type, int $id): Collection
    {
        return $this->salesDocumentRepository->revisionsOf($this->salesDocumentService->find($workspace, $type, $id));
    }

    public function find(Workspace $workspace, DocumentType $type, int $id, int $revision): SalesDocumentRevision
    {
        return $this->salesDocumentRepository->findRevision($this->salesDocumentService->find($workspace, $type, $id), $revision)
            ?? abort(404, 'Revision not found.');
    }
}
