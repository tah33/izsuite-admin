<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\EcommerceQuoteRequestRepository;
use App\Services\Crm\LeadVisibility;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The business's requests for a quotation (Doc 19 section 19.30 - Phase
 * H4a), under View Orders: the Quote Requests page, and one request whole. A
 * request is seen where its storefront's location may be used, as an order
 * is, and to anyone else it is not found. From Phase H4b each names the CRM
 * lead it became only to a user who may open that lead (showLeads()), and
 * from Phase H4c its draft quotation only to one who may open that
 * (showQuotations()).
 */
class EcommerceQuoteRequestService
{
    public function __construct(
        protected EcommerceQuoteRequestRepository $requests,
        protected WorkspaceAccess $access,
        protected CrmLeadRepository $leads,
        protected LeadVisibility $visibility,
    ) {}

    /** $filters: storefront_id and search. */
    public function list(Workspace $workspace, User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $page = $this->requests->paginateFor($workspace, $this->access->locations(), $filters, $perPage);
        $this->showLeads($workspace, $user, $page->items());
        $this->showQuotations($workspace, $user, $page->items());

        return $page;
    }

    public function find(Workspace $workspace, User $user, int $id): EcommerceQuoteRequest
    {
        $quote = $this->requests->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such request.');
        $this->showLeads($workspace, $user, [$quote]);
        $this->showQuotations($workspace, $user, [$quote]);

        return $quote;
    }

    /**
     * Phase H4c: on each of $quotes, the quotation it became as $user may
     * open it - `shown_quotation`, its id and number (none while a draft) -
     * none otherwise: Sales on, View Quotations theirs, and the quotation at
     * a location they may use.
     *
     * @param  list<EcommerceQuoteRequest>  $quotes
     */
    private function showQuotations(Workspace $workspace, User $user, array $quotes): void
    {
        $may       = WorkspaceApps::for($workspace)->has('sales') && $this->access->allows($user, $workspace, 'sales.quotations.view');
        $locations = $this->access->locations();

        foreach ($quotes as $quote) {
            $quotation = $may ? $quote->quotation : null;
            $seen      = $quotation && ($locations === null || $quotation->location_id === null || in_array($quotation->location_id, $locations, true));

            $quote->setAttribute('shown_quotation', $seen ? ['id' => $quotation->id, 'number' => $quotation->number] : null);
        }
    }

    /**
     * Phase H4b: on each of $quotes, the lead it became as $user may open it
     * - `shown_lead_id`, none otherwise: CRM on, View Leads theirs, and the
     * lead among those they see (their own, their teams', all, or the queue).
     *
     * @param  list<EcommerceQuoteRequest>  $quotes
     */
    private function showLeads(Workspace $workspace, User $user, array $quotes): void
    {
        $ids  = array_values(array_unique(array_filter(array_map(fn (EcommerceQuoteRequest $quote) => $quote->crm_lead_id, $quotes))));
        $seen = $ids !== [] && WorkspaceApps::for($workspace)->has('crm') && $this->access->allows($user, $workspace, 'crm.leads.view')
            ? $this->leads->visibleAmong($workspace, $this->visibility->scopeFor($workspace, $user), $ids)->keys()->all()
            : [];

        foreach ($quotes as $quote) {
            $quote->setAttribute('shown_lead_id', in_array($quote->crm_lead_id, $seen, true) ? $quote->crm_lead_id : null);
        }
    }
}
