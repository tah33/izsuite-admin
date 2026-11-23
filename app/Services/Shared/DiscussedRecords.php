<?php

namespace App\Services\Shared;

use App\Enums\DocumentType;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Api\Crm\CrmLeadService;
use App\Services\Api\Crm\CrmOpportunityService;
use App\Services\Api\Ecommerce\EcommerceOrderService;
use App\Services\Api\Expenses\ExpenseService;
use App\Services\Api\Purchases\PurchaseService;
use App\Services\Api\Sales\SalesDocumentService;
use App\Services\Crm\LeadVisibility;
use App\Services\Crm\OpportunityVisibility;
use Illuminate\Http\Request;

/**
 * Finds the record a comments or activity route is about (Doc 14 Phase D):
 * a sales document of its {kind}, a purchase, an expense or - Doc 17 Phases
 * B2b and D2b - a lead or an opportunity of the business. Another business's
 * record, or one of another kind, is a 404, as on its own page; so is a lead
 * or an opportunity the user does not see. The route's middleware has
 * already checked the app, the View permission and the location.
 */
class DiscussedRecords
{
    public function __construct(
        protected SalesDocumentService $salesDocumentService,
        protected PurchaseService $purchaseService,
        protected ExpenseService $expenseService,
        protected CrmLeadService $crmLeadService,
        protected LeadVisibility $leadVisibility,
        protected CrmOpportunityService $crmOpportunityService,
        protected OpportunityVisibility $opportunityVisibility,
        protected EcommerceOrderService $ecommerceOrderService,
    ) {}

    /** $family is the route's: "sales", "purchases", "expenses", "crm" (leads) or "opportunities". $kind is a sales route's {kind}. */
    public function find(Workspace $workspace, string $family, ?string $kind, int $id): DiscussedRecord
    {
        return match ($family) {
            'sales'         => $this->sales($workspace, (string) $kind, $id),
            'purchases'     => $this->purchase($workspace, $id),
            'expenses'      => $this->expense($workspace, $id),
            'crm'           => $this->lead($workspace, $id),
            'opportunities' => $this->opportunity($workspace, $id),
            'ecommerce_orders' => $this->ecommerceOrder($workspace, $id),
        };
    }

    /** Doc 19 Phase F1: an online order at a location the user may use - its timeline only. */
    private function ecommerceOrder(Workspace $workspace, int $id): DiscussedRecord
    {
        $order = $this->ecommerceOrderService->find($workspace, $id);

        return new DiscussedRecord($order, 'ecommerce_orders', 'ecommerce.orders.view', $this->ecommerceOrderService->describe($order), "/ecommerce/orders/{$order->id}");
    }

    /** A lead the signed-in user sees; who else sees it decides who a comment may mention. */
    private function lead(Workspace $workspace, int $id): DiscussedRecord
    {
        $lead = $this->crmLeadService->find($workspace, app(Request::class)->user(), $id);

        return new DiscussedRecord($lead, 'crm', 'crm.leads.view', $this->crmLeadService->describe($lead), "/crm/leads/{$lead->id}",
            fn (User $user) => $this->leadVisibility->sees($workspace, $user, $lead));
    }

    /** An opportunity the signed-in user sees, the same way. */
    private function opportunity(Workspace $workspace, int $id): DiscussedRecord
    {
        $opportunity = $this->crmOpportunityService->find($workspace, app(Request::class)->user(), $id);

        return new DiscussedRecord($opportunity, 'opportunities', 'crm.opportunities.view', $this->crmOpportunityService->describe($opportunity), "/crm/opportunities/{$opportunity->id}",
            fn (User $user) => $this->opportunityVisibility->sees($workspace, $user, $opportunity));
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
