<?php

namespace App\Services\Api\Transfer;

use App\Http\Requests\Api\Contacts\SaveContactRequest;
use App\Models\Admin\Language;
use App\Models\User\Contact;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Services\Api\Contacts\ContactDuplicates;
use App\Services\Api\Contacts\ContactService;
use App\Support\Countries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contacts in and out of CSV (Doc 03 section 15). Countries are written by
 * name and read by name or code; tags are separated by semicolons; payment
 * terms are days ("Due on Receipt" and "Net 30" read too). A row without a
 * type is a business, one without a role a customer.
 *
 * Possible duplicates follow Doc 03 section 12, against the business's
 * contacts and against the file's other rows.
 */
class ContactTransfer implements Transfer
{
    use ReadsCells;

    private const TYPES = ['business' => ['company', 'organisation', 'organization'], 'individual' => ['person', 'personal']];

    private const ROLES = ['customer' => ['client'], 'supplier' => ['vendor'], 'both' => ['customerandsupplier', 'customersupplier']];

    private const STATUSES = ['active' => ['yes', 'enabled'], 'inactive' => ['no', 'disabled']];

    private const SHIPPING = ['line1', 'line2', 'city', 'state', 'postal_code', 'country'];

    public function __construct(
        protected ContactRepository $contactRepository,
        protected ContactService $contactService,
        protected ContactDuplicates $contactDuplicates,
    ) {}

    public function plural(): string
    {
        return 'contacts';
    }

    public function fields(Workspace $workspace): array
    {
        $country  = 'A country name, or its two-letter code.';
        $currency = 'A currency code the business uses, such as '.$workspace->base_currency.'. Empty means the base currency.';
        $terms    = 'Days, or "Due on Receipt", or "Net 30".';

        return [
            'contact_type'             => $this->field('Contact Type', hint: 'Business or Individual. Empty means Business.', aliases: ['type']),
            'role'                     => $this->field('Contact Role', hint: 'Customer, Supplier or Both. Empty means Customer.', aliases: ['role']),
            'name'                     => $this->field('Name', true, aliases: ['contact name', 'business name', 'full name', 'company', 'company name', 'customer name', 'supplier name']),
            'trading_name'             => $this->field('Trading Name', aliases: ['trade name', 'doing business as']),
            'contact_person'           => $this->field('Contact Person', aliases: ['contact']),
            'job_title'                => $this->field('Job Title', aliases: ['title', 'position']),
            'phone'                    => $this->field('Phone', aliases: ['phone number', 'mobile', 'mobile number', 'telephone']),
            'alt_phone'                => $this->field('Alternative Phone', aliases: ['other phone', 'phone 2', 'alternate phone']),
            'email'                    => $this->field('Email', aliases: ['email address', 'e-mail']),
            'website'                  => $this->field('Website', aliases: ['web', 'url']),
            'preferred_language'       => $this->field('Preferred Language', hint: 'A language name or code.', aliases: ['language']),
            'tags'                     => $this->field('Tags', hint: 'Separated by semicolons or commas.'),
            'billing_line1'            => $this->field('Billing Address Line 1', aliases: ['address', 'address line 1', 'street']),
            'billing_line2'            => $this->field('Billing Address Line 2', aliases: ['address line 2']),
            'billing_city'             => $this->field('Billing City', aliases: ['city', 'town']),
            'billing_state'            => $this->field('Billing State', aliases: ['state', 'region', 'province', 'county']),
            'billing_postal_code'      => $this->field('Billing Postal Code', aliases: ['postal code', 'postcode', 'zip', 'zip code']),
            'billing_country'          => $this->field('Billing Country', hint: $country, aliases: ['country']),
            'shipping_same_as_billing' => $this->field('Shipping Same As Billing', hint: 'Yes or No. Empty is Yes, unless a shipping address is given.'),
            'shipping_line1'           => $this->field('Shipping Address Line 1'),
            'shipping_line2'           => $this->field('Shipping Address Line 2'),
            'shipping_city'            => $this->field('Shipping City'),
            'shipping_state'           => $this->field('Shipping State'),
            'shipping_postal_code'     => $this->field('Shipping Postal Code'),
            'shipping_country'         => $this->field('Shipping Country', hint: $country),
            'sales_currency'           => $this->field('Default Sales Currency', hint: $currency),
            'customer_payment_terms'   => $this->field('Customer Payment Terms', hint: $terms),
            'purchase_currency'        => $this->field('Default Purchase Currency', hint: $currency),
            'supplier_payment_terms'   => $this->field('Supplier Payment Terms', hint: $terms),
            'tax_number'               => $this->field('Tax ID', aliases: ['tax number', 'vat number', 'gst number', 'bin', 'tin']),
            'tax_country'              => $this->field('Tax Country', hint: $country),
            'tax_exemption_number'     => $this->field('Tax Exemption Number'),
            'notes'                    => $this->field('Notes', aliases: ['note', 'comments']),
            'status'                   => $this->field('Status', hint: 'Active or Inactive. Empty means Active.'),
        ];
    }

    public function example(): array
    {
        return [
            'contact_type'           => 'Business',
            'role'                   => 'Customer',
            'name'                   => 'Uddin Traders Ltd',
            'contact_person'         => 'Rahim Uddin',
            'phone'                  => '+880 1711-000000',
            'email'                  => 'accounts@uddin.example',
            'tags'                   => 'Wholesale; Dhaka',
            'billing_line1'          => 'House 12, Road 5',
            'billing_city'           => 'Dhaka',
            'billing_postal_code'    => '1207',
            'billing_country'        => 'Bangladesh',
            'customer_payment_terms' => 'Net 30',
            'status'                 => 'Active',
        ];
    }

    public function exportColumns(Workspace $workspace): array
    {
        $address = fn (string $prefix, string $label) => [
            "{$label} Address Line 1" => fn (Contact $contact) => $contact->{"{$prefix}_line1"},
            "{$label} Address Line 2" => fn (Contact $contact) => $contact->{"{$prefix}_line2"},
            "{$label} City"           => fn (Contact $contact) => $contact->{"{$prefix}_city"},
            "{$label} State"          => fn (Contact $contact) => $contact->{"{$prefix}_state"},
            "{$label} Postal Code"    => fn (Contact $contact) => $contact->{"{$prefix}_postal_code"},
            "{$label} Country"        => fn (Contact $contact) => Countries::name($contact->{"{$prefix}_country"}),
        ];

        return [
            'Contact Number'            => fn (Contact $contact) => $contact->contact_number,
            'Contact Type'              => fn (Contact $contact) => ucfirst($contact->contact_type),
            'Contact Role'              => fn (Contact $contact) => ucfirst($contact->role),
            'Name'                      => fn (Contact $contact) => $contact->name,
            'Trading Name'              => fn (Contact $contact) => $contact->trading_name,
            'Contact Person'            => fn (Contact $contact) => $contact->contact_person,
            'Job Title'                 => fn (Contact $contact) => $contact->job_title,
            'Phone'                     => fn (Contact $contact) => $contact->phone,
            'Alternative Phone'         => fn (Contact $contact) => $contact->alt_phone,
            'Email'                     => fn (Contact $contact) => $contact->email,
            'Website'                   => fn (Contact $contact) => $contact->website,
            'Preferred Language'        => fn (Contact $contact) => $contact->preferred_language,
            'Tags'                      => fn (Contact $contact) => implode('; ', $contact->tags ?? []),
            ...$address('billing', 'Billing'),
            'Shipping Same As Billing'  => fn (Contact $contact) => (bool) $contact->shipping_same_as_billing,
            ...$address('shipping', 'Shipping'),
            'Default Sales Currency'    => fn (Contact $contact) => $contact->sales_currency,
            'Customer Payment Terms'    => fn (Contact $contact) => $contact->customer_payment_terms,
            'Default Purchase Currency' => fn (Contact $contact) => $contact->purchase_currency,
            'Supplier Payment Terms'    => fn (Contact $contact) => $contact->supplier_payment_terms,
            // Tax details only while the business uses taxes, as on its pages.
            ...($workspace->usesTaxes() ? [
                'Tax ID'               => fn (Contact $contact) => $contact->tax_number,
                'Tax Country'          => fn (Contact $contact) => Countries::name($contact->tax_country),
                'Tax Exemption Number' => fn (Contact $contact) => $contact->tax_exemption_number,
            ] : []),
            'Notes'                     => fn (Contact $contact) => $contact->notes,
            'Status'                    => fn (Contact $contact) => $contact->is_active ? 'Active' : 'Inactive',
        ];
    }

    public function exportQuery(Workspace $workspace, array $filters): Builder
    {
        return $this->contactRepository->queryFor($workspace, $filters, $workspace->usesTaxes());
    }

    public function payload(Workspace $workspace, array $cells): array
    {
        $errors  = [];
        $payload = [];

        foreach (['name', 'trading_name', 'contact_person', 'job_title', 'phone', 'alt_phone', 'email', 'tax_number', 'tax_exemption_number', 'notes'] as $key) {
            $payload[$key] = $this->text($cells, $key);
        }

        foreach (['line1', 'line2', 'city', 'state', 'postal_code'] as $part) {
            $payload["billing_{$part}"]  = $this->text($cells, "billing_{$part}");
            $payload["shipping_{$part}"] = $this->text($cells, "shipping_{$part}");
        }

        $type   = $this->choice($this->text($cells, 'contact_type'), self::TYPES);
        $role   = $this->choice($this->text($cells, 'role'), self::ROLES);
        $status = $this->choice($this->text($cells, 'status'), self::STATUSES);

        if ($type === false) {
            $errors['contact_type'] = 'Write Business or Individual.';
        }

        if ($role === false) {
            $errors['role'] = 'Write Customer, Supplier or Both.';
        }

        if ($status === false) {
            $errors['status'] = 'Write Active or Inactive.';
        }

        $payload['contact_type'] = $type ?: Contact::TYPE_BUSINESS;
        $payload['role']         = $role ?: Contact::ROLE_CUSTOMER;
        $payload['is_active']    = $status !== 'inactive';

        // A website typed without its scheme ("example.com") gets https://, as on the form.
        $website            = $this->text($cells, 'website');
        $payload['website'] = $website && ! preg_match('#^https?://#i', $website) ? "https://{$website}" : $website;

        foreach (['billing_country', 'shipping_country', 'tax_country'] as $key) {
            $typed         = $this->text($cells, $key);
            $payload[$key] = Countries::codeFor($typed);

            if ($typed !== null && $payload[$key] === null) {
                $errors[$key] = "\"{$typed}\" is not a country this list knows.";
            }
        }

        $language = $this->text($cells, 'preferred_language');

        $payload['preferred_language'] = $language === null ? null : Language::active()
            ->where(fn ($query) => $query->whereRaw('LOWER(code) = ?', [mb_strtolower($language)])->orWhereRaw('LOWER(name) = ?', [mb_strtolower($language)]))
            ->value('code');

        if ($language !== null && $payload['preferred_language'] === null) {
            $errors['preferred_language'] = "\"{$language}\" is not one of the available languages.";
        }

        $payload['tags'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,]/', (string) $this->text($cells, 'tags')) ?: []))));

        // The base currency is what no default means.
        foreach (['sales_currency', 'purchase_currency'] as $key) {
            $currency      = strtoupper((string) $this->text($cells, $key));
            $payload[$key] = $currency === '' || $currency === $workspace->base_currency ? null : $currency;
        }

        foreach (['customer_payment_terms', 'supplier_payment_terms'] as $key) {
            $terms         = $this->terms($this->text($cells, $key));
            $payload[$key] = $terms === false ? null : $terms;

            if ($terms === false) {
                $errors[$key] = 'Write a number of days, "Due on Receipt" or "Net 30".';
            }
        }

        // Empty, it follows the row: a shipping address of its own means "no".
        $same        = $this->choice($this->text($cells, 'shipping_same_as_billing'), self::YES_NO);
        $ownShipping = (bool) array_filter(array_map(fn (string $part) => $payload["shipping_{$part}"] ?? null, self::SHIPPING));

        $payload['shipping_same_as_billing'] = is_string($same) ? $same === 'yes' : ! $ownShipping;

        if ($same === false) {
            $errors['shipping_same_as_billing'] = 'Write Yes or No.';
        }

        return [$payload, $errors];
    }

    public function errors(Workspace $workspace, array $payload): array
    {
        return $this->formErrors(SaveContactRequest::class, $payload, ['workspace' => $workspace]);
    }

    public function keys(array $payload): array
    {
        return ['duplicates' => $this->contactDuplicates->keys($payload), 'unique' => []];
    }

    public function duplicatesOf(Workspace $workspace, array $payload): array
    {
        return array_map(fn (array $duplicate) => [
            'id'         => $duplicate['id'],
            'number'     => $duplicate['contact_number'],
            'name'       => $duplicate['name'],
            'matched_on' => $duplicate['matched_on'],
        ], $this->contactDuplicates->find($workspace, $payload));
    }

    public function create(Workspace $workspace, array $payload): Model
    {
        return $this->contactService->import($workspace, $payload);
    }

    /** Payment terms in days: "30", "Net 30", "Due on Receipt" (0). Null for none, false for anything else. */
    private function terms(?string $value): int|false|null
    {
        if ($value === null) {
            return null;
        }

        if (in_array($this->plain($value), ['dueonreceipt', 'onreceipt', 'immediate', 'immediately'], true)) {
            return 0;
        }

        return preg_match('/^(?:net\s*)?(\d{1,3})(?:\s*days?)?$/i', trim($value), $match) ? (int) $match[1] : false;
    }

    /** @return array{label: string, required: bool, aliases: list<string>, hint: ?string} */
    private function field(string $label, bool $required = false, ?string $hint = null, array $aliases = []): array
    {
        return ['label' => $label, 'required' => $required, 'aliases' => $aliases, 'hint' => $hint];
    }
}
