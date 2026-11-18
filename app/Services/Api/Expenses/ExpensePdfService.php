<?php

namespace App\Services\Api\Expenses;

use App\Models\User\Workspace;
use App\Repositories\User\ExpensePaymentRepository;
use App\Services\Documents\DocumentPdf;
use App\Services\Shared\WorkspaceAccess;

/**
 * An expense's record (Doc 10 section 10: Preview, Download Expense Record,
 * Print) and an expense payment's voucher (Doc 06 section 13) as PDFs - a
 * draft marked DRAFT, a void one VOID, a reversed voucher REVERSED.
 *
 * A PDF shows the record's values, so it needs the app's Values (Doc 12
 * section 14).
 */
class ExpensePdfService
{
    public function __construct(
        protected ExpenseService $expenseService,
        protected ExpensePaymentService $expensePaymentService,
        protected ExpensePaymentRepository $expensePaymentRepository,
        protected DocumentPdf $documentPdf,
        protected WorkspaceAccess $access,
    ) {}

    /** @return array{0: string, 1: string} the file name and the PDF */
    public function expense(Workspace $workspace, int $id): array
    {
        $this->access->authorize('expenses.values.view');

        return $this->documentPdf->expense($workspace, $this->expenseService->find($workspace, $id));
    }

    /** @return array{0: string, 1: string} */
    public function voucher(Workspace $workspace, int $id): array
    {
        $this->access->authorize('expenses.values.view');

        $payment = $this->expensePaymentService->find($workspace, $id);

        return $this->documentPdf->expenseVoucher($workspace, $this->expensePaymentRepository->loadForVoucher($payment));
    }
}
