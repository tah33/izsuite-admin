<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CrmLead;
use App\Models\User\CrmOption;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Services\Crm\LeadInterests;
use App\Services\Crm\LeadRouting;
use App\Services\Crm\LeadScoring;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Services\User\Apps\WorkspaceApps;

/**
 * A request for a quotation made a CRM lead (Doc 19 section 19.30 - Phase
 * H4b), while Settings → eCommerce says so and CRM is on:
 *
 * - its customer's name, company, e-mail and phone; from Website - the
 *   business's lead source of that key, CRM's starter lists started for a
 *   business with none yet - its details the store's name and the request's
 *   number;
 * - at the storefront's location, the date wanted its expected purchase
 *   date, the delivery its address, the message its notes, and its products
 *   the interested items with how many;
 * - without an owner: the assignment rules give it one, as for a lead added
 *   to the queue, and that owner is told; with none it waits in the queue;
 * - the business's custom fields left empty, required ones too - a member
 *   fills them as they edit it - scored as any lead is, and in the Activity
 *   Log, put down to the customer.
 */
class QuoteRequestLeads
{
    /** The starter lead source a store's requests come from, by izSuite's key. */
    private const SOURCE = 'website';

    public function __construct(
        protected CrmLeadRepository $leads,
        protected CrmOptionRepository $options,
        protected LeadInterests $interests,
        protected LeadRouting $routing,
        protected LeadScoring $scoring,
        protected Notifier $notifier,
    ) {}

    /** $request's lead, while CRM is on - or none: Settings → eCommerce's choice is StorefrontQuoteRequests'. Made inside the request's own transaction. */
    public function make(Storefront $storefront, EcommerceQuoteRequest $request): ?CrmLead
    {
        $workspace = $storefront->workspace;

        if (! WorkspaceApps::for($workspace)->has('crm')) {
            return null;
        }

        $items = $this->interests->rows($workspace, $request->items->map(fn (EcommerceQuoteRequestItem $item) => [
            'item_id' => $item->item_id, 'name' => $item->title, 'quantity' => (string) $item->quantity,
        ])->all());

        $lead = $this->leads->create($workspace, [
            'name'                   => $request->customer_name,
            'business_name'          => $request->company,
            'email'                  => $request->customer_email,
            'phone'                  => $request->customer_phone,
            'source_id'              => $this->source($workspace)?->id,
            'source_details'         => "{$storefront->name}: {$request->number}",
            'location_id'            => $storefront->location_id,
            'status'                 => CrmLead::NEW,
            'owner_id'               => null,
            'expected_purchase_date' => $request->required_on?->toDateString(),
            'address_line1'          => $request->delivery_address,
            'address_city'           => $request->delivery_city,
            'address_country'        => $request->delivery_country,
            'notes'                  => $request->message,
            'custom_fields'          => [],
        ]);

        $this->leads->replaceItems($lead, $items);

        // As a lead added to the queue: the first assignment rule that fits gives it its owner.
        $routed = $this->routing->route($workspace, $lead, array_values(array_filter(array_column($items, 'item_id'))));

        if ($routed) {
            $this->leads->update($lead, ['owner_id' => $routed['owner_id'], 'status' => CrmLead::ASSIGNED, 'assigned_at' => now(), 'first_assigned_at' => now()]);
        }

        return $lead;
    }

    /** Once $lead is saved with its $request: scored, in the Activity Log - put down to the customer - and its owner, if the rules gave it one, told. */
    public function made(Storefront $storefront, EcommerceQuoteRequest $request, CrmLead $lead): void
    {
        $workspace = $storefront->workspace;

        $this->scoring->rescore($workspace, $lead->id);
        ActivityLogService::recordCustomer($workspace, 'created', "Added the lead {$lead->name} from the request for a quotation {$request->number} on {$storefront->name}", $lead);

        $lead = $this->leads->loadShown($lead);

        if ($lead->owner) {
            $this->notifier->send($workspace, [$lead->owner], new Notice(
                trigger: 'leads.assigned',
                key: "leads.assigned:{$lead->id}:{$lead->owner->id}:".$lead->assigned_at?->getTimestamp(),
                title: "A lead from {$storefront->name}: {$lead->name}",
                body: "Its request for a quotation, {$request->number}",
                link: "/crm/leads/{$lead->id}",
                subject: $lead,
                email: true,
            ));
        }
    }

    /** The business's Website lead source, while it is on - CRM's starter lists started first for a business that has none. */
    private function source(Workspace $workspace): ?CrmOption
    {
        if (! $this->options->existsFor($workspace)) {
            $this->options->addStarter($workspace);
        }

        $source = $this->options->findByKey($workspace, 'lead_source', self::SOURCE);

        return $source?->is_active ? $source : null;
    }
}
