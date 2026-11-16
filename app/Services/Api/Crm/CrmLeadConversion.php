<?php

namespace App\Services\Api\Crm;

use App\Models\User\Contact;
use App\Models\User\CrmDuplicateReview;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\CrmPipeline;
use App\Models\User\CrmStage;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmDuplicateReviewRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmPipelineRepository;
use App\Services\Api\Contacts\ContactDuplicates;
use App\Services\Api\Contacts\ContactService;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\CrmLeadRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Converting a qualified lead (Doc 17 section 17.11 - Phase C1):
 *
 * 1. The business's contacts that may be the lead's are offered, with what
 *    matched (ContactDuplicates: name, e-mail, phone); any contact can be
 *    chosen, or a new one made from the lead, for whoever may create
 *    contacts.
 * 2. The contact becomes a customer: a supplier becomes both.
 * 3. An opportunity is made at an open stage of the business's pipeline,
 *    with the stage's probability, carrying over the lead's owner,
 *    location, team, priority, source, need, next action and notes.
 * 4. The lead is Converted, for good. Its files, comments and timeline stay
 *    with it, and the opportunity keeps its lead.
 *
 * Nothing about it is a sale.
 */
class CrmLeadConversion
{
    use RefusesConflicts;

    public function __construct(
        protected CrmLeadService $leadService,
        protected CrmLeadRepository $leads,
        protected CrmOpportunityRepository $opportunities,
        protected CrmPipelineRepository $pipelines,
        protected CrmSettingService $settings,
        protected CrmLeadRules $rules,
        protected ContactDuplicates $contactDuplicates,
        protected ContactRepository $contacts,
        protected ContactService $contactService,
        protected RowLockRepository $rows,
        protected WorkspaceAccess $access,
        protected CrmDuplicateReviewRepository $reviews,
    ) {}

    /**
     * What Convert offers: the contacts that may be the lead's, the
     * pipeline's open stages, what the opportunity starts with, and whether
     * a new contact may be made.
     */
    public function preview(Workspace $workspace, User $user, int $id): array
    {
        $lead   = $this->allowed($workspace, $user, $id);
        $stages = $this->openStages($this->pipeline($workspace));

        return [
            'matches'            => $this->matches($workspace, $lead),
            'stages'             => $stages,
            'can_create_contact' => $this->access->can('contacts.create'),
            'defaults'           => [
                'name'                => $lead->business_name ?: $lead->name,
                'stage_id'            => $stages->first()?->id,
                'expected_value'      => $lead->estimated_value,
                'currency'            => $lead->currency ?? $workspace->base_currency,
                'expected_close_date' => $lead->expected_purchase_date?->toDateString(),
            ],
        ];
    }

    /** $data is validated (see ConvertCrmLeadRequest): contact_id - none for a new contact - and the opportunity. */
    public function convert(Workspace $workspace, User $user, int $id, array $data): CrmLead
    {
        $lead     = $this->allowed($workspace, $user, $id);
        $pipeline = $this->pipeline($workspace);
        $stage    = $this->openStages($pipeline)->firstWhere('id', (int) $data['opportunity']['stage_id'])
            ?? throw ValidationException::withMessages(['opportunity.stage_id' => "Choose one of the pipeline's open stages."]);
        $contact  = $this->contact($workspace, $data['contact_id'] ?? null);

        DB::transaction(function () use ($workspace, $user, $lead, $pipeline, $stage, $contact, $data) {
            // Converted once: a second request waits here, then finds it converted.
            if ($this->rows->lock($lead)->status !== CrmLead::QUALIFIED) {
                $this->refuse('This lead is converted already.');
            }

            $contact ??= $this->contactService->create($workspace, $this->contactFrom($lead));

            if ($contact->role === Contact::ROLE_SUPPLIER) {
                $this->contacts->update($contact, ['role' => Contact::ROLE_BOTH]);

                ActivityLogService::record('updated', 'Made contact "'.Str::limit($contact->name, 100).'" a customer as well, converting '.$this->leadService->describe($lead), $contact, ['source' => 'api']);
            }

            $opportunity = $this->opportunities->create($workspace, [
                ...$this->carried($lead),
                'name'                => $data['opportunity']['name'],
                'contact_id'          => $contact->id,
                'pipeline_id'         => $pipeline->id,
                'stage_id'            => $stage->id,
                'status'              => CrmOpportunity::OPEN,
                'probability'         => $stage->probability,
                'expected_value'      => $data['opportunity']['expected_value'],
                'currency'            => $data['opportunity']['currency'],
                'expected_close_date' => $data['opportunity']['expected_close_date'],
                'stage_entered_at'    => now(),
            ]);

            // What the lead was interested in, and its first stage (Phase D1).
            $this->opportunities->replaceItems($opportunity, $lead->interestedItems->map(fn ($item) => [
                'item_id'  => $item->item_id,
                'name'     => $item->name,
                'quantity' => $item->quantity,
            ])->all());
            $this->opportunities->enterStage($opportunity, $stage->id, $user->id);

            $this->leads->update($lead, [
                'status'         => CrmLead::CONVERTED,
                'contact_id'     => $contact->id,
                'opportunity_id' => $opportunity->id,
                'converted_at'   => now(),
                'converted_by'   => $user->id,
            ]);

            $named = '"'.Str::limit($opportunity->name, 100).'"';

            ActivityLogService::record('updated', 'Converted '.$this->leadService->describe($lead)." into opportunity {$named} for contact \"".Str::limit($contact->name, 100).'"', $lead, [
                'source' => 'api', 'status' => CrmLead::CONVERTED, 'contact_id' => $contact->id, 'opportunity_id' => $opportunity->id,
            ]);
            ActivityLogService::record('created', "Added opportunity {$named} from ".$this->leadService->describe($lead), $opportunity, ['source' => 'api']);
        });

        return $this->leads->loadShown($lead);
    }

    /** $lead - one $user sees - while it is qualified. */
    private function allowed(Workspace $workspace, User $user, int $id): CrmLead
    {
        $lead = $this->leadService->find($workspace, $user, $id);

        if (! $this->rules->allows($lead, 'convert')) {
            $this->refuse($lead->status === CrmLead::CONVERTED ? 'This lead is converted already.' : 'A lead is qualified before it is converted.');
        }

        return $lead;
    }

    /** One of the business's contacts - or none, for a new one, from whoever may create contacts. */
    private function contact(Workspace $workspace, mixed $id): ?Contact
    {
        if ($id !== null) {
            $contact = $this->contacts->findFor($workspace, (int) $id) ?? throw ValidationException::withMessages(['contact_id' => 'Choose one of your contacts.']);

            // One merged into another (Doc 17 Phase C3) is kept only for its records.
            if ($contact->isMerged()) {
                throw ValidationException::withMessages(['contact_id' => 'That contact was merged into another. Choose the one it was merged into.']);
            }

            return $contact;
        }

        if (! $this->access->can('contacts.create')) {
            throw ValidationException::withMessages(['contact_id' => 'Choose one of your contacts: making a new one needs Create Contacts.']);
        }

        return null;
    }

    /** The business's pipeline: its first, the only one until Phase D. */
    private function pipeline(Workspace $workspace): CrmPipeline
    {
        $this->settings->start($workspace);

        return $this->pipelines->getFor($workspace)->first();
    }

    /** @return Collection<int, CrmStage> its open stages switched on, in order */
    private function openStages(CrmPipeline $pipeline): Collection
    {
        return $pipeline->stages->filter(fn (CrmStage $stage) => $stage->is_active && $stage->category === CrmStage::OPEN)->values();
    }

    /**
     * The contacts that may be the lead's: those it was linked to (Phase C2)
     * first, then those it matches but was not told apart from.
     */
    private function matches(Workspace $workspace, CrmLead $lead): array
    {
        $reviews = $this->reviews->forLeads([$lead->id])->where('other_type', CrmDuplicateReview::CONTACT);
        $linked  = $this->contacts->findManyFor($workspace, $reviews->where('decision', CrmDuplicateReview::LINKED)->pluck('other_id')->all());
        $decided = $reviews->pluck('other_id')->all();

        return [
            ...$linked->map(fn (Contact $contact) => ['id' => $contact->id, 'contact_number' => $contact->contact_number, 'name' => $contact->name, 'trading_name' => $contact->trading_name, 'matched_on' => ['linked']])->values(),
            ...array_values(array_filter($this->contactDuplicates->find($workspace, $this->probe($lead)), fn (array $match) => ! in_array($match['id'], $decided, true))),
        ];
    }

    /** What ContactDuplicates compares the lead on: its business and person, e-mail and phone. */
    private function probe(CrmLead $lead): array
    {
        return [
            'name'         => $lead->business_name ?: $lead->name,
            'trading_name' => $lead->business_name ? $lead->name : null,
            'email'        => $lead->email,
            'phone'        => $lead->phone,
        ];
    }

    /** A new contact from the lead: a customer, its business or person, how to reach them and where they are. */
    private function contactFrom(CrmLead $lead): array
    {
        $business = filled($lead->business_name);

        return array_filter([
            'contact_type'        => $business ? Contact::TYPE_BUSINESS : Contact::TYPE_INDIVIDUAL,
            'role'                => Contact::ROLE_CUSTOMER,
            'name'                => $business ? $lead->business_name : $lead->name,
            'contact_person'      => $business ? $lead->name : null,
            'job_title'           => $lead->job_title,
            'email'               => $lead->email,
            'phone'               => $lead->phone,
            'preferred_language'  => $lead->preferred_language,
            'tags'                => $lead->tags ?: null,
            'billing_line1'       => $lead->address_line1,
            'billing_line2'       => $lead->address_line2,
            'billing_city'        => $lead->address_city,
            'billing_state'       => $lead->address_state,
            'billing_postal_code' => $lead->address_postal_code,
            'billing_country'     => $lead->address_country,
            // Its possible copies were shown before it was asked for.
            'allow_duplicates'    => true,
        ], fn ($value) => $value !== null);
    }

    /** What the opportunity carries over from the lead. */
    private function carried(CrmLead $lead): array
    {
        return [
            'lead_id'       => $lead->id,
            'owner_id'      => $lead->owner_id,
            'location_id'   => $lead->location_id,
            'sales_team_id' => $lead->sales_team_id,
            'source_id'     => $lead->source_id,
            'priority'      => $lead->priority,
            'requirements'  => $lead->customer_need,
            'next_action'   => $lead->next_action,
            'notes'         => $lead->notes,
        ];
    }
}
