<?php

namespace App\Services\Shared;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Services\Api\Expenses\ExpenseService;
use App\Services\Api\Purchases\PurchaseService;
use App\Services\Api\Sales\SalesDocumentService;

/**
 * Finds the record a comments or activity route is about (Doc 14 Phase D):
 * a sales document of its {kind}, a purchase or an expense of the business.
 * Another business's record, or one of another kind, is a 404, as on its
 * own page. The route's middleware has already checked the app, the View
 * permission and the location.
 */
class DiscussedRecords
{
    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected PurchaseService $purchaseService,
        protected ExpenseService $expenseService,
    ) {}

    /** $family is the route's: "sales", "purchases" or "expenses". $kind is a sales route's {kind}. */
    public function find(Workspace $workspace, string $family, ?string $kind, int $id): DiscussedRecord
    {
        return match ($family) {
            'sales'     => $this->sales($workspace, (string) $kind, $id),
            'purchases' => $this->purchase($workspace, $id),
            'expenses'  => $this->expense($workspace, $id),
        };
    }

    private function sales(Workspace $workspace, string $kind, int $id): DiscussedRecord
    {
        $type     = DocumentType::fromKind($kind) ?? abort(404);
        $document = $this->salesDocumentService->find($workspace, $type, $id);

        return new DiscussedRecord($document, 'sales', $type->permissionGroup().'.view', $this->salesDocumentService->describe($document), "/sales/{$kind}/{$document->id}");
    }

    private function purchase(Workspace $workspace, int $id): DiscussedRecord
    {
        $purchase = $this->purchaseService->find($workspace, $id);

        return new DiscussedRecord($purchase, 'purchases', 'purchases.purchases.view', $this->purchaseService->describe($purchase), "/purchases/{$purchase->id}");
    }

    private function expense(Workspace $workspace, int $id): DiscussedRecord
    {
        $expense = $this->expenseService->find($workspace, $id);

        return new DiscussedRecord($expense, 'expenses', 'expenses.expenses.view', $this->expenseService->describe($expense), "/expenses/{$expense->id}");
    }
}
