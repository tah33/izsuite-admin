<?php

namespace App\Services\Shared;

use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Repositories\User\ExpenseRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\SalesDocumentRepository;

/**
 * Whether a business has financial activity (Doc 11 sections 5 and 7): a
 * document issued or sent, a purchase or an expense recorded, or a payment
 * account given an opening balance or money moved through it. From then on
 * its base currency is locked, and changing its country asks first.
 */
class BusinessActivity
{
    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected PurchaseRepository $purchaseRepository,
        protected ExpenseRepository $expenseRepository,
        protected PaymentAccountRepository $paymentAccountRepository,
        protected AccountTransactionRepository $accountTransactionRepository,
    ) {}

    public function exists(Workspace $workspace): bool
    {
        return $this->salesDocumentRepository->hasTransactions($workspace)
            || $this->purchaseRepository->hasTransactions($workspace)
            || $this->expenseRepository->hasTransactions($workspace)
            || $this->paymentAccountRepository->hasOpeningBalance($workspace)
            || $this->accountTransactionRepository->existsForWorkspace($workspace);
    }
}
