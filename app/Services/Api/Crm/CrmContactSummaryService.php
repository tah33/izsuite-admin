<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Services\Crm\LeadVisibility;
use App\Services\Crm\OpportunityForecast;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;

/**
 * The CRM part of a contact's profile (Doc 17 section 17.20 - Phase F4),
 * beside its sales documents, balance, activities and timeline: where they
 * came from - the source of the first lead converted into them, else of their
 * first opportunity with one - and their opportunities, open, won and lost.
 *
 * The open ones count with their whole and weighted values, the won with
 * their whole, in the base currency at today's rate - an estimate, as the
 * forecast's; one in a currency without a rate today is left out and
 * counted. Only what the user sees: their own records, their teams' or all,
 * at their locations; archived opportunities are left out, as the lists
 * leave them.
 */
class CrmContactSummaryService
{
    /** As many opportunities as the profile lists: more than a contact has in practice. */
    public const LISTED = 50;

    public function __construct(
        protected ContactRepository $contacts,
        protected CrmLeadRepository $leads,
        protected CrmOpportunityRepository $opportunities,
        protected LeadVisibility $leadVisibility,
        protected OpportunityVisibility $opportunityVisibility,
        protected OpportunityForecast $forecast,
        protected CrmSettingService $settings,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * The contact $id is's CRM part, as $user may see it: its source, the
     * base currency, its open, won and lost opportunities' counts and values,
     * and the opportunities - the open ones by their closing day, then the
     * ended, the latest first.
     */
    public function summary(Workspace $workspace, User $user, int $id): array
    {
        $contact = $this->contacts->findFor($workspace, $id) ?? abort(404, 'Contact not found.');

        $opportunities = $this->access->allows($user, $workspace, 'crm.opportunities.view')
            ? $this->opportunities->forContactProfile($workspace, $this->opportunityVisibility->scopeFor($workspace, $user), $contact->id)
            : collect();
        $leads = $this->settings->usesLeads($workspace) && $this->access->allows($user, $workspace, 'crm.leads.view')
            ? $this->leads->convertedInto($workspace, $this->leadVisibility->scopeFor($workspace, $user), $contact->id)
            : collect();

        $open = $opportunities->where('status', CrmOpportunity::OPEN);
        $won  = $opportunities->where('status', CrmOpportunity::WON);

        return [
            'source'        => $this->source($leads, $opportunities),
            'currency'      => $workspace->base_currency,
            'open'          => ['count' => $open->count(), 'whole' => $this->forecast->total($workspace, $open, false), 'weighted' => $this->forecast->total($workspace, $open)['total']],
            'won'           => ['count' => $won->count(), 'whole' => $this->forecast->total($workspace, $won, false)],
            'lost'          => ['count' => $opportunities->where('status', CrmOpportunity::LOST)->count()],
            'opportunities' => $this->ordered($opportunities)->take(self::LISTED)->values(),
        ];
    }

    /**
     * Where the contact came from: the source of the first lead converted
     * into them that has one, with the lead - else that of their first
     * opportunity with one.
     *
     * @return array{name: string, lead: array{id: int, name: string}|null}|null
     */
    private function source(Collection $leads, Collection $opportunities): ?array
    {
        $lead = $leads->first(fn (CrmLead $lead) => $lead->source !== null);

        if ($lead) {
            return ['name' => $lead->source->name, 'lead' => ['id' => $lead->id, 'name' => $lead->name]];
        }

        $opportunity = $opportunities->first(fn (CrmOpportunity $opportunity) => $opportunity->source !== null);

        return $opportunity ? ['name' => $opportunity->source->name, 'lead' => null] : null;
    }

    /** The open ones by their closing day, the soonest first; then the won and lost, the latest ended first. */
    private function ordered(Collection $opportunities): Collection
    {
        $open   = $opportunities->where('status', CrmOpportunity::OPEN)
            ->sortBy(fn (CrmOpportunity $opportunity) => [(string) ($opportunity->getRawOriginal('expected_close_date') ?? '9999-12-31'), $opportunity->id]);
        $ended  = $opportunities->where('status', '!=', CrmOpportunity::OPEN)
            ->sortByDesc(fn (CrmOpportunity $opportunity) => [(string) $opportunity->getRawOriginal('closed_on'), $opportunity->id]);

        return $open->concat($ended);
    }
}
