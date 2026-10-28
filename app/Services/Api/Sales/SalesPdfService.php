<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\SalesPaymentRepository;
use App\Services\Documents\DocumentPdf;

/**
 * A sales document's or a payment receipt's PDF (Doc 06 sections 13 and 15),
 * to preview, download or print - in any status: a draft says DRAFT across
 * it, a void one VOID, a reversed receipt REVERSED.
 */
class SalesPdfService
{
    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected SalesPaymentService $salesPaymentService,
        protected SalesPaymentRepository $salesPaymentRepository,
        protected DocumentPdf $documentPdf,
    ) {}

    /** @return array{0: string, 1: string} the file name and the PDF */
    public function document(Workspace $workspace, DocumentType $type, int $id): array
    {
        return $this->documentPdf->sales($workspace, $this->salesDocumentService->find($workspace, $type, $id));
    }

    /** @return array{0: string, 1: string} */
    public function receipt(Workspace $workspace, int $id): array
    {
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
