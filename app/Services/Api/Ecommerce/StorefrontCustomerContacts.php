<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Contact;
use App\Models\User\CrmOption;
use App\Models\User\EcommerceOrder;
use App\Models\User\MarketingConsent;
use App\Models\User\Storefront;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\MarketingConsentRepository;
use App\Services\Shared\ActivityLogService;

/**
 * An online order's contact, found or made (Doc 19 section 19.17 - Phase
 * D4), and the customer's marketing consent:
 *
 * - a guest's e-mail is not verified, so the order is linked only to a
 *   contact an earlier guest order made for that same e-mail (still active,
 *   still with it - or the one it was merged into). Otherwise a new shared
 *   contact is made, a customer, with the details and addresses given: a
 *   contact of the business's own with that e-mail is never linked on an
 *   unverified e-mail, and the two are merged by hand if they are one
 *   (Contacts' duplicates). Verified customers come with Phase E;
 * - marketing consent, given apart from the terms, is recorded as the
 *   contact's: Promotions and Offers by e-mail, from the website form - with
 *   the order as its evidence. Settings → CRM's consent purposes are started
 *   for a business that has none yet.
 */
class StorefrontCustomerContacts
{
    /** The consent purpose an order's marketing consent is for, by izSuite's key. */
    private const PURPOSE = 'promotions';

    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected ContactRepository $contacts,
        protected CrmOptionRepository $options,
        protected MarketingConsentRepository $consents,
    ) {}

    /**
     * The contact for an order numbered $number with $data's customer and
     * addresses, and whether it was made now.
     *
     * @return array{contact: Contact, created: bool}
     */
    public function contactFor(Storefront $storefront, array $data, string $number): array
    {
        $workspace = $storefront->workspace;
        $customer  = $data['customer'];
        $email     = trim($customer['email']);

        if ($kept = $this->orders->contactMadeFor($workspace, $email)) {
            return ['contact' => $kept, 'created' => false];
        }

        $company  = trim((string) ($customer['company'] ?? ''));
        $billing  = $data['billing'];
        $shipping = ($data['delivery'] ?? null) === EcommerceOrder::SHIPPING && ! ($data['shipping_same'] ?? true) ? $data['shipping'] : null;

        $contact = $this->contacts->create($workspace, [
            'contact_type'             => $company !== '' ? Contact::TYPE_BUSINESS : Contact::TYPE_INDIVIDUAL,
            'role'                     => Contact::ROLE_CUSTOMER,
            'name'                     => $company !== '' ? $company : trim($customer['name']),
            'contact_person'           => $company !== '' ? trim($customer['name']) : null,
            'email'                    => $email,
            'phone'                    => trim((string) ($customer['phone'] ?? '')) ?: null,
            'preferred_language'       => $storefront->default_language,
            ...$this->address('billing', $billing),
            'shipping_same_as_billing' => $shipping === null,
            ...($shipping ? $this->address('shipping', $shipping) : []),
            'notes'                    => "Made by the online order {$number} on {$storefront->name}.",
            'is_active'                => true,
        ]);

        ActivityLogService::recordCustomer($workspace, 'created', "Added the contact {$contact->name} from the online order {$number}", $contact);

        return ['contact' => $contact, 'created' => true];
    }

    /** The customer's marketing consent, given at checkout: Promotions and Offers by e-mail, from the website form. */
    public function recordConsent(Storefront $storefront, Contact $contact, EcommerceOrder $order): void
    {
        $workspace = $storefront->workspace;

        if (! $this->options->existsFor($workspace)) {
            $this->options->addStarter($workspace);
        }

        $purposes = $this->options->getFor($workspace)->where('kind', 'consent_purpose')->where('is_active', true);
        $purpose  = $purposes->firstWhere('key', self::PURPOSE) ?? $purposes->first();

        if (! $purpose instanceof CrmOption) {
            return;
        }

        $this->consents->create($workspace, [
            'contact_id'  => $contact->id,
            'purpose_id'  => $purpose->id,
            'channel'     => 'email',
            'status'      => MarketingConsent::GIVEN,
            'source'      => 'web_form',
            'recorded_at' => now(),
            'recorded_by' => null,
            'evidence'    => "Online order {$order->number} on {$storefront->name}",
        ]);

        ActivityLogService::recordCustomer($workspace, 'updated', "Recorded marketing consent given for {$purpose->name} by e-mail, at the online order {$order->number}", $contact);
    }

    /** $address as a contact keeps it, under $side (billing or shipping). */
    private function address(string $side, array $address): array
    {
        return [
            "{$side}_line1"       => trim((string) ($address['line1'] ?? '')) ?: null,
            "{$side}_line2"       => trim((string) ($address['line2'] ?? '')) ?: null,
            "{$side}_city"        => trim((string) ($address['city'] ?? '')) ?: null,
            "{$side}_state"       => trim((string) ($address['region'] ?? '')) ?: null,
            "{$side}_postal_code" => trim((string) ($address['postal_code'] ?? '')) ?: null,
            "{$side}_country"     => strtoupper((string) ($address['country'] ?? '')) ?: null,
        ];
    }
}
