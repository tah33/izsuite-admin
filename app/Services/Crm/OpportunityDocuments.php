<?php

namespace App\Services\Crm;

use App\Enums\DocumentType;
use App\Models\User\CrmOpportunity;
use App\Models\User\CrmOpportunityItem;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sales documents made from a CRM opportunity (Doc 17 section 17.27 - Phase
 * F1): a quotation, proforma invoice, sales order or invoice made from one
 * the user sees, while CRM is on, for its contact, while it is open or won
 * and not archived. Such a document starts with the opportunity's contact,
 * currency, location, owner, team and interested items, and is linked to it
 * (`opportunity_id`) - and so is what is converted from it.
 *
 * Its value stays its own: however many documents it has, they add nothing
 * to it, or to the pipeline and forecast it counts in.
 */
class OpportunityDocuments
{
    /** The most an opportunity's card lists, the newest first. */
    public const LISTED = 100;

    public function __construct(
        protected CrmOpportunityRepository $opportunities,
        protected SalesDocumentRepository $documents,
        protected OpportunityVisibility $visibility,
        protected SalesDocumentRules $documentRules,
        protected BusinessCalendar $calendar,
        protected WorkspaceAccess $access,
    ) {}

    /** The opportunity $id is, while CRM is on, if $user may view opportunities and sees it. */
    public function seen(Workspace $workspace, User $user, int $id): ?CrmOpportunity
    {
        if (! WorkspaceApps::for($workspace)->has('crm') || ! $this->access->allows($user, $workspace, 'crm.opportunities.view')) {
            return null;
        }

        return $this->opportunities->findFor($workspace, $id, $this->visibility->scopeFor($workspace, $user));
    }

    /**
     * Why a new document cannot be made from $opportunity - one the user
     * does not see is null - for the contact $contactId: [field, message],
     * or null when it can.
     *
     * @return array{0: string, 1: string}|null
     */
    public function refusal(?CrmOpportunity $opportunity, ?int $contactId): ?array
    {
        return match (true) {
            $opportunity === null                          => ['opportunity_id', 'Choose an opportunity you can see, while CRM is on.'],
            $opportunity->archived_at !== null             => ['opportunity_id', 'This opportunity is archived. Restore it before making documents from it.'],
            $opportunity->status === CrmOpportunity::LOST  => ['opportunity_id', 'This opportunity was lost. Reopen it before making documents from it.'],
            $contactId !== null && $contactId !== $opportunity->contact_id => ['contact_id', 'A document made from an opportunity is for its contact.'],
            default                                        => null,
        };
    }

    /**
     * What a new document made from $opportunity starts with: its contact,
     * currency, location, owner and team, and a line for each interested
     * item - the item at its sales price, or what was named, unpriced.
     */
    public function start(CrmOpportunity $opportunity): array
    {
        $opportunity = $this->opportunities->loadForDocuments($opportunity);

        return [
            'id'            => $opportunity->id,
            'name'          => $opportunity->name,
            'contact'       => $opportunity->contact,
            'currency'      => $opportunity->currency,
            'location_id'   => $opportunity->location_id,
            'owner_id'      => $opportunity->owner_id,
            'sales_team_id' => $opportunity->sales_team_id,
            'lines'         => $opportunity->interestedItems->map(fn (CrmOpportunityItem $row) => $this->line($row))->all(),
        ];
    }

    /**
     * $opportunity's documents the user may see - the kinds their role
     * views, at the locations they may use - the newest first, each with
     * its status as shown (an offer past its expiry reads expired).
     *
     * @return Collection<int, SalesDocument>
     */
    public function list(Workspace $workspace, User $user, int $id): Collection
    {
        $opportunity = $this->seen($workspace, $user, $id) ?? abort(404, 'Opportunity not found.');
        $today       = $this->calendar->today($workspace)->toDateString();

        return $this->madeFor($workspace, ['opportunity_id' => $opportunity->id])
            ->each(function (SalesDocument $document) use ($today) {
                $document->setAttribute('shown_status', $this->documentRules->status($document, $today));
            });
    }

    /**
     * The documents of the contact or opportunity $where names - ['contact_id'
     * => …] or ['opportunity_id' => …] - that the signed-in user may see:
     * the kinds their role views, at the locations they may use.
     *
     * @return Collection<int, SalesDocument>
     */
    public function madeFor(Workspace $workspace, array $where): Collection
    {
        $types = array_map(fn (DocumentType $type) => $type->value, $this->access->viewable(array_values(DocumentType::KINDS)));

        return $this->documents->madeFor($workspace, $where, $types, $this->access->locations(), self::LISTED);
    }

    /** The opportunity a document's page names, for whoever sees it while CRM is on - else null. */
    public function shownLink(SalesDocument $document): ?array
    {
        $request     = app(Request::class);
        $workspace   = $request->attributes->get('workspace');
        $opportunity = $document->opportunity;

        if (! $opportunity || ! $request->user() || ! $workspace instanceof Workspace || ! WorkspaceApps::for($workspace)->has('crm')
            || ! $this->access->can('crm.opportunities.view') || ! $this->visibility->sees($workspace, $request->user(), $opportunity)) {
            return null;
        }

        return ['id' => $opportunity->id, 'name' => $opportunity->name];
    }

    /** A line for an interested item: its item while it is on - its name, description, unit, price and tax - or the name it was given. */
    private function line(CrmOpportunityItem $row): array
    {
        $item = $row->item?->is_active ? $row->item : null;

        return [
            'item_id'     => $item?->id,
            'name'        => $item?->name ?? $row->name ?? '',
            'description' => $item?->sales_description,
            'unit'        => $item?->unit,
            'unit_price'  => $item ? ($item->isCombo() && $item->combo_pricing === 'sum' ? $item->componentTotal() : $item->sale_price) : null,
            'tax_rate_id' => $item?->sales_tax_id,
            'quantity'    => (string) $row->quantity,
        ];
    }
}
