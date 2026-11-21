<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Contact;
use App\Models\User\CrmOption;
use App\Models\User\EcommerceOrder;
use App\Models\User\MarketingConsent;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmOptionRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\MarketingConsentRepository;
use App\Repositories\User\StorefrontCustomerRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
 *   (Contacts' duplicates);
 * - Phase E2: a customer account, once its e-mail is verified, is linked to
 *   its contact - linkAccount() - and its e-mail's guest orders become its
 *   own;
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
        protected StorefrontCustomerRepository $accounts,
    ) {}

    /**
     * Phase E2: the contact of $customer's account, its e-mail verified: its
     * own while active (or the one it was merged into); else the one its
     * e-mail's guest orders made; else the business's one customer with that
     * e-mail - two or more is not certain, so none of them; else a new
     * customer contact. Linked to the account, whose e-mail's orders of no
     * account become its own - and, from Phase E3, given the language the
     * account chose. Whether the contact was made now comes with it.
     *
     * @return array{contact: Contact, created: bool}
     */
    public function linkAccount(Storefront $storefront, StorefrontCustomer $customer): array
    {
        $workspace = $storefront->workspace;
        $only      = fn () => ($found = $this->contacts->customersWithEmail($workspace, $customer->email))->count() === 1 ? $found->first() : null;
        $contact   = ($customer->contact_id ? $this->contacts->findCurrent($workspace, $customer->contact_id) : null)
            ?? $this->orders->contactMadeFor($workspace, $customer->email)
            ?? $only();
        $created   = $contact === null;

        $contact ??= $this->contacts->create($workspace, [
            'contact_type'       => Contact::TYPE_INDIVIDUAL,
            'role'               => Contact::ROLE_CUSTOMER,
            'name'               => $customer->name,
            'email'              => $customer->email,
            'phone'              => $customer->phone,
            'preferred_language' => $customer->language ?: $storefront->default_language,
            'notes'              => "Made by the store account of {$customer->name} on {$storefront->name}.",
            'is_active'          => true,
        ]);

        if ($created) {
            ActivityLogService::recordCustomer($workspace, 'created', "Added the contact {$contact->name} from a store account on {$storefront->name}", $contact);
        }

        if ($customer->contact_id !== $contact->id) {
            $this->accounts->update($customer, ['contact_id' => $contact->id]);

            if (! $created) {
                ActivityLogService::recordCustomer($workspace, 'updated', "Linked the store account of {$customer->name} ({$customer->email}) to the contact {$contact->name}", $contact);

                // Phase E3: a language the account chose - before its e-mail was verified, say - is its contact's once linked.
                if ($customer->language && $contact->preferred_language !== $customer->language) {
                    $this->contacts->update($contact, ['preferred_language' => $customer->language]);
                }
            }
        }

        $this->orders->adoptFor($customer);

        return ['contact' => $contact, 'created' => $created];
    }

    /** Phase E2: $customer's account opened again - its contact and orders let go until it is verified again. */
    public function unlinkAccount(StorefrontCustomer $customer): void
    {
        $this->orders->releaseFrom($customer);
        $this->accounts->update($customer, ['contact_id' => null]);
    }

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

    /**
     * The customer's marketing consent, given at checkout: Promotions and
     * Offers by e-mail, from the website form. From Phase E3, as an account's
     * is, nothing is recorded while it stands given already.
     */
    public function recordConsent(Storefront $storefront, Contact $contact, EcommerceOrder $order): void
    {
        $workspace = $storefront->workspace;
        $purpose   = $this->purposeOf($workspace, true);

        if (! $purpose || $this->gives($this->consents->standing($workspace, 'contact_id', $contact->id, $purpose->id, 'email'))) {
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

    /**
     * Phase E3: whether $customer's contact takes marketing by e-mail - the
     * standing answer for Promotions and Offers, given and not lapsed - or
     * null while the account has no contact (its e-mail not verified).
     */
    public function marketingOf(Storefront $storefront, StorefrontCustomer $customer): ?bool
    {
        $contact = $customer->contact_id ? $this->contacts->findCurrent($storefront->workspace, $customer->contact_id) : null;

        if (! $contact) {
            return null;
        }

        $purpose = $this->purposeOf($storefront->workspace, false);

        return $purpose !== null && $this->gives($this->consents->standing($storefront->workspace, 'contact_id', $contact->id, $purpose->id, 'email'));
    }

    /**
     * Phase E3: marketing by e-mail given - or withdrawn - by $customer from
     * their account, as their contact's: Promotions and Offers, from the
     * website form. Nothing is recorded when it stands as asked already.
     */
    public function setMarketing(Storefront $storefront, StorefrontCustomer $customer, bool $given): void
    {
        $workspace = $storefront->workspace;
        $contact   = ($customer->contact_id ? $this->contacts->findCurrent($workspace, $customer->contact_id) : null)
            ?? throw ValidationException::withMessages(['marketing' => 'Verify your e-mail first: then choose what we send you.']);
        $purpose   = $this->purposeOf($workspace, true);

        if (! $purpose) {
            return;
        }

        DB::transaction(function () use ($workspace, $storefront, $contact, $purpose, $given) {
            $standing = $this->consents->standing($workspace, 'contact_id', $contact->id, $purpose->id, 'email');

            if ($given === $this->gives($standing)) {
                return;
            }

            if ($given) {
                $this->consents->create($workspace, [
                    'contact_id' => $contact->id, 'purpose_id' => $purpose->id, 'channel' => 'email', 'status' => MarketingConsent::GIVEN,
                    'source' => 'web_form', 'recorded_at' => now(), 'recorded_by' => null, 'evidence' => "Store account on {$storefront->name}",
                ]);
            } else {
                $this->consents->update($standing, ['status' => MarketingConsent::WITHDRAWN, 'withdrawn_at' => now(), 'withdrawn_by' => null, 'withdrawal_note' => "Withdrawn by the customer from their store account on {$storefront->name}."]);
            }

            ActivityLogService::recordCustomer($workspace, 'updated', ($given ? 'Recorded marketing consent given' : 'Recorded marketing consent withdrawn')." for {$purpose->name} by e-mail, from the store account on {$storefront->name}", $contact);
        });
    }

    /** Phase E3: the language $customer chose, as their contact's too. */
    public function languageChosen(StorefrontCustomer $customer, string $language): void
    {
        $contact = $customer->contact_id ? $this->contacts->findCurrent($customer->workspace, $customer->contact_id) : null;

        if ($contact && $contact->preferred_language !== $language) {
            $this->contacts->update($contact, ['preferred_language' => $language]);
        }
    }

    /** The consent purpose an order's or an account's marketing is for: Promotions and Offers, else the first - Settings → CRM's started when $start and it has none. */
    private function purposeOf(Workspace $workspace, bool $start): ?CrmOption
    {
        if ($start && ! $this->options->existsFor($workspace)) {
            $this->options->addStarter($workspace);
        }

        $purposes = $this->options->getFor($workspace)->where('kind', 'consent_purpose')->where('is_active', true);
        $purpose  = $purposes->firstWhere('key', self::PURPOSE) ?? $purposes->first();

        return $purpose instanceof CrmOption ? $purpose : null;
    }

    /** Whether $standing answer gives consent now: given, and not lapsed. */
    private function gives(?MarketingConsent $standing): bool
    {
        return $standing?->status === MarketingConsent::GIVEN && ($standing->expires_on === null || $standing->expires_on->gte(today()));
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
