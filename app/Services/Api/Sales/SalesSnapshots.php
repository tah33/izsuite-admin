<?php

namespace App\Services\Api\Sales;

use App\Enums\FileCollection;
use App\Models\User\Contact;
use App\Models\User\Location;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Documents\PaymentDetails;
use App\Support\AddressFormat;
use App\Support\Countries;

/**
 * What a numbered document keeps as it was (Doc 06 section 6, Doc 14 section
 * 14.13): the business and the customer when it was issued or sent - so
 * editing either later never rewrites it - and, for a revision, the whole
 * document before it changed (Doc 08 section 12).
 */
class SalesSnapshots
{
    /** What a revision compares: what the customer sees, not its status or number. */
    private const COMPARED = [
        'contact_id', 'location_id', 'document_date', 'due_date', 'expiry_date', 'currency', 'exchange_rate', 'customer_reference', 'sales_reference',
        'discount_type', 'discount_value', 'notes', 'terms', 'payment_instructions', 'grand_total', 'lines',
        // The full Sales app's (Doc 16 Phase A).
        'salesperson_id', 'sales_team_id', 'billing_address', 'shipping_address', 'expected_delivery_date', 'shipping_method', 'shipping_terms',
    ];

    public function __construct(
        protected BusinessSettingRepository $businessSettingRepository,
        protected WorkspaceFileRepository $workspaceFileRepository,
        protected PaymentDetails $paymentDetails,
    ) {}

    /**
     * The business as a document shows it (Doc 11 section 4) - its legal name
     * and trading name, registration number, address, contacts and tax
     * numbers - with its location and the lines that location adds to the
     * header (Doc 12 section 17). The country is printed by name. With
     * $currency, also the payment accounts it shows customers in that
     * currency (Doc 05 section 9) - for the documents customers pay from.
     */
    public function business(Workspace $workspace, ?Location $location, ?string $currency = null): array
    {
        $settings = $this->businessSettingRepository->findFor($workspace);

        return [
            'name'                => $settings?->business_name ?: $workspace->name,
            'trading_name'        => $settings?->trading_name,
            'registration_number' => $settings?->registration_number,
            'address'             => $this->address($settings?->country, [
                'line1' => $settings?->address_line_1, 'line2' => $settings?->address_line_2, 'city' => $settings?->city,
                'region' => $settings?->state, 'postal' => $settings?->zip_code,
            ]),
            'phone'               => $settings?->business_contact_number,
            'email'               => $settings?->email,
            'website'             => $settings?->website,
            // Not registered for tax (Doc 11 section 9), it keeps its numbers but prints none.
            'tax_numbers'         => $settings?->tax_registration_status === 'not_registered' ? [] : array_values(array_filter([
                $settings?->tax_1_no ? ['name' => $settings->tax_1_name, 'number' => $settings->tax_1_no] : null,
                $settings?->tax_2_no ? ['name' => $settings->tax_2_name, 'number' => $settings->tax_2_no] : null,
            ])),
            'logo_file_id'        => $this->workspaceFileRepository->inCollection($workspace, FileCollection::Logo)->first()?->id,
            'location'            => $location?->name,
            'location_header'     => $location?->document_header,
            'payment_accounts'    => $currency ? $this->paymentDetails->for($workspace, $currency, $location?->id) : [],
            // "Powered by izSuite" as the plan has it now, kept with the document (Doc 13 section 18).
            'powered_by'          => ! $workspace->terms()?->removes_branding,
        ];
    }

    /**
     * The customer as $document shows them: with the document's own billing
     * and shipping address where it names one (Doc 16 Phase A), each written
     * as its country writes addresses.
     */
    public function customerOf(SalesDocument $document): array
    {
        $customer = $this->customer($document->contact);

        foreach (['billing_address', 'shipping_address'] as $side) {
            if ($own = $document->{$side}) {
                $customer[$side] = $this->addressLines($own);
            }
        }

        return $customer;
    }

    /** A document's own address - a sales document's or a delivery's (Doc 16) - as its country writes it. */
    public function addressLines(array $own): array
    {
        return $this->address($own['country'] ?? null, [
            'line1' => $own['line1'] ?? null, 'line2' => $own['line2'] ?? null, 'city' => $own['city'] ?? null,
            'region' => $own['state'] ?? null, 'postal' => $own['postal_code'] ?? null,
        ]);
    }

    public function customer(Contact $contact): array
    {
        $address = fn (string $side) => $this->address($contact->{"{$side}_country"}, [
            'line1' => $contact->{"{$side}_line1"}, 'line2' => $contact->{"{$side}_line2"}, 'city' => $contact->{"{$side}_city"},
            'region' => $contact->{"{$side}_state"}, 'postal' => $contact->{"{$side}_postal_code"},
        ]);

        return [
            'number'               => $contact->contact_number,
            'name'                 => $contact->name,
            'trading_name'         => $contact->trading_name,
            'contact_person'       => $contact->contact_person,
            'email'                => $contact->email,
            'phone'                => $contact->phone,
            'billing_address'      => $address('billing'),
            'shipping_address'     => $contact->shipping_same_as_billing ? null : $address('shipping'),
            'tax_number'           => $contact->tax_number,
            'tax_country'          => $contact->tax_country,
            'tax_treatment'        => $contact->tax_treatment?->value,
            'tax_exemption_number' => $contact->tax_exemption_number,
        ];
    }

    /** The document whole, as it is now - kept when a sent quotation or proforma is changed. */
    public function document(SalesDocument $document): array
    {
        return [
            ...$document->only([
                'number', 'revision', 'status', 'contact_id', 'location_id', 'document_date', 'due_date', 'expiry_date', 'currency', 'exchange_rate',
                'price_tax_mode', 'customer_reference', 'sales_reference', 'discount_type', 'discount_value', 'subtotal', 'discount_total',
                'tax_total', 'grand_total', 'notes', 'terms', 'payment_instructions', 'customer_snapshot',
                ...SalesDocument::FULL_FIELDS,
            ]),
            'status' => $document->status->value,
            'lines'  => $document->lines->map(fn (SalesDocumentLine $line) => $line->only([
                'item_id', 'name', 'description', 'quantity', 'unit', 'unit_price', 'discount_type', 'discount_value', 'discount_amount',
                'amount', 'tax_name', 'tax_code', 'tax_rate', 'tax_treatment', 'tax_amount', 'components', 'warranty',
            ]))->all(),
        ];
    }

    /** Whether two versions of a document differ in anything the customer sees. */
    public function differ(array $before, array $after): bool
    {
        return json_encode(array_intersect_key($before, array_flip(self::COMPARED))) !== json_encode(array_intersect_key($after, array_flip(self::COMPARED)));
    }

    /**
     * An address's lines as its country writes them (Doc 15 Phase F3a: the
     * country's address format), the country printed by name - addresses keep
     * its code. @return list<string>
     */
    private function address(?string $country, array $parts): array
    {
        return AddressFormat::lines(Countries::addressFormat($country), [
            ...$parts,
            'country' => $country ? Countries::name($country) ?? $country : null,
        ]);
    }
}
