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
    ];

    public function __construct(
        protected BusinessSettingRepository $businessSettingRepository,
        protected WorkspaceFileRepository $workspaceFileRepository,
    ) {}

    public function business(Workspace $workspace, ?Location $location): array
    {
        $settings = $this->businessSettingRepository->findFor($workspace);

        return [
            'name'        => $settings?->business_name ?: $workspace->name,
            'address'     => array_values(array_filter([$settings?->landmark, $settings?->city, $settings?->state, $settings?->zip_code, $settings?->country])),
            'phone'       => $settings?->business_contact_number,
            'website'     => $settings?->website,
            'tax_numbers' => array_values(array_filter([
                $settings?->tax_1_no ? ['name' => $settings->tax_1_name, 'number' => $settings->tax_1_no] : null,
                $settings?->tax_2_no ? ['name' => $settings->tax_2_name, 'number' => $settings->tax_2_no] : null,
            ])),
            'logo_file_id' => $this->workspaceFileRepository->inCollection($workspace, FileCollection::Logo)->first()?->id,
            'location'    => $location?->name,
        ];
    }

    public function customer(Contact $contact): array
    {
        // The country by name, as it is printed: contacts keep its code.
        $address = fn (string $side) => array_values(array_filter([
            $contact->{"{$side}_line1"}, $contact->{"{$side}_line2"}, $contact->{"{$side}_city"},
            $contact->{"{$side}_state"}, $contact->{"{$side}_postal_code"}, Countries::name($contact->{"{$side}_country"}),
        ]));

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
}
