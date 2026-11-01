<?php

namespace App\Services\Api\Purchases;

use App\Models\User\Workspace;
use App\Repositories\User\SupplierPaymentRepository;
use App\Services\Documents\DocumentPdf;

/**
 * A purchase's record (Doc 09 section 10: Preview, Download Purchase Record,
 * Print) and a supplier payment's voucher (Doc 06 section 13) as PDFs - a
 * draft marked DRAFT, a void one VOID, a reversed voucher REVERSED.
 */
class PurchasePdfService
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected SupplierPaymentService $supplierPaymentService,
        protected SupplierPaymentRepository $supplierPaymentRepository,
        protected DocumentPdf $documentPdf,
    ) {}

    /** @return array{0: string, 1: string} the file name and the PDF */
    public function purchase(Workspace $workspace, int $id): array
    {
        return $this->documentPdf->purchase($workspace, $this->purchaseService->find($workspace, $id));
    }

    /** @return array{0: string, 1: string} */
    public function voucher(Workspace $workspace, int $id): array
    {
        $payment = $this->supplierPaymentService->find($workspace, $id);

        return $this->documentPdf->supplierVoucher($workspace, $this->supplierPaymentRepository->loadForVoucher($payment));
    }
}
