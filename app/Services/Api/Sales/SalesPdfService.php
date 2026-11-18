<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\SalesPaymentRepository;
use App\Services\Documents\DocumentPdf;
use App\Services\Shared\WorkspaceAccess;

/**
 * A sales document's or a payment receipt's PDF (Doc 06 sections 13 and 15),
 * to preview, download or print - in any status: a draft says DRAFT across
 * it, a void one VOID, a reversed receipt REVERSED.
 *
 * A PDF shows the record's values, so it needs the app's Values (Doc 12
 * section 14).
 */
class SalesPdfService
{
    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected SalesPaymentService $salesPaymentService,
        protected SalesPaymentRepository $salesPaymentRepository,
        protected DocumentPdf $documentPdf,
        protected WorkspaceAccess $access,
    ) {}

    /** @return array{0: string, 1: string} the file name and the PDF */
    public function document(Workspace $workspace, DocumentType $type, int $id): array
    {
        $this->access->authorize('sales.values.view');

        return $this->documentPdf->sales($workspace, $this->salesDocumentService->find($workspace, $type, $id));
    }

    /** @return array{0: string, 1: string} */
    public function receipt(Workspace $workspace, int $id): array
    {
        $this->access->authorize('sales.values.view');

        return $this->documentPdf->receipt($workspace, $this->receiptFor($workspace, $id));
    }

    /** A payment, with what its receipt shows. A refund has no receipt, and answers like one that does not exist. */
    public function receiptFor(Workspace $workspace, int $id): SalesPayment
    {
        $payment = $this->salesPaymentService->find($workspace, $id);

        abort_unless($payment->kind === SalesPayment::PAYMENT, 404, 'Only a payment has a receipt.');

        return $this->salesPaymentRepository->loadForReceipt($payment);
    }
}
