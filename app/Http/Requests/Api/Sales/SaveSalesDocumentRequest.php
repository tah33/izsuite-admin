<?php

namespace App\Http\Requests\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\Contact;
use App\Models\User\Location;
use App\Enums\SalesDocumentStatus;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSalesDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ISO currency codes are upper case. A document in the base currency has
     * an exchange rate of 1. While the business does not use taxes, lines
     * carry none (Doc 08 section 17).
     *
     * A draft may be saved half filled in: lines left blank are dropped, and
     * a date or currency left empty is the business's today and its base.
     */
    protected function prepareForValidation(): void
    {
        $workspace = $this->attributes->get('workspace');

        if ($workspace && $this->isDraft()) {
            if (is_array($this->input('lines'))) {
                $this->merge(['lines' => array_values(array_filter($this->input('lines'), fn ($line) => ! is_array($line) || ! $this->blankLine($line)))]);
            }

            if (blank($this->input('document_date'))) {
                $this->merge(['document_date' => app(BusinessCalendar::class)->today($workspace)->toDateString()]);
            }

            if (blank($this->input('currency'))) {
                $this->merge(['currency' => $workspace->base_currency]);
            }
        }

        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }

        if ($this->input('currency') === $workspace?->base_currency) {
            $this->merge(['exchange_rate' => '1']);
        }

        if (is_array($this->input('lines')) && ! $workspace?->usesTaxes()) {
            $this->merge(['lines' => array_map(fn ($line) => is_array($line) ? [...$line, 'tax_rate_id' => null] : $line, $this->input('lines'))]);
        }
    }

    /** A new document, or one still a draft: what it leaves out is asked for when it is issued or sent. */
    private function isDraft(): bool
    {
        if (! $this->route('id')) {
            return true;
        }

        $workspace = $this->attributes->get('workspace');
        $type      = DocumentType::fromKind((string) $this->route('kind'));

        return $workspace && $type
            && app(SalesDocumentRepository::class)->findFor($workspace, $type, (int) $this->route('id'))?->status === SalesDocumentStatus::Draft;
    }

    private function blankLine(array $line): bool
    {
        return empty($line['item_id']) && blank($line['name'] ?? null) && blank($line['description'] ?? null) && blank($line['unit_price'] ?? null);
    }

    /**
     * The same for adding and for editing (Doc 08 section 4). A customer, the
     * date, the currency, the location where the business has one, and at
     * least one line are required; an invoice may leave its due date to its
     * payment terms, and a quotation or proforma needs its expiry date. A
     * currency other than the base needs its exchange rate.
     *
     * Each line is an item or a description, more than zero of it, at a price
     * that is never negative (Doc 08 section 4) - returns are credit notes.
     *
     * A draft needs none of it - SalesDocumentFlow asks for what is missing
     * when it is issued or sent - but what it does fill in must be valid.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');
        $type      = DocumentType::fromKind((string) $this->route('kind'));
        $inBusiness = fn (string $model) => Rule::exists($model, 'id')->where('workspace_id', $workspace?->id);
        $foreign   = $this->input('currency') !== $workspace?->base_currency;
        $draft     = $this->isDraft();
        $must      = fn (bool $needed = true) => $needed && ! $draft ? 'required' : 'nullable';

        return [
            'contact_id'             => [$must(), 'integer', $inBusiness(Contact::class)],
            'location_id'            => [$must($workspace && app(LocationRepository::class)->existsFor($workspace)), 'nullable', 'integer', $inBusiness(Location::class)],
            'document_date'          => ['required', 'date_format:Y-m-d'],
            'due_date'               => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'expiry_date'            => [$must((bool) $type?->isOffer()), 'nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'payment_terms'          => ['nullable', 'integer', 'min:0', 'max:365'],
            'currency'               => ['required', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
            'exchange_rate'          => [$must($foreign), 'nullable', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'customer_reference'     => ['nullable', 'string', 'max:100'],
            'sales_reference'        => ['nullable', 'string', 'max:100'],
            'discount_type'          => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value'         => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'terms'                  => ['nullable', 'string', 'max:10000'],
            'payment_instructions'   => ['nullable', 'string', 'max:5000'],
            'lines'                  => $draft ? ['nullable', 'array', 'max:200'] : ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id'        => ['nullable', 'integer'],
            'lines.*.name'           => [$must(), 'string', 'max:255'],
            'lines.*.description'    => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity'       => [$must(), 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'lines.*.unit'           => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price'     => [$must(), 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'lines.*.discount_type'  => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'lines.*.tax_rate_id'    => ['nullable', 'integer'],
        ];
    }

    /** What needs the database or the settings - see SalesDocumentChecks. */
    public function after(): array
    {
        return [fn (Validator $validator) => app(SalesDocumentChecks::class)->check(
            $validator,
            $this->attributes->get('workspace'),
            DocumentType::fromKind((string) $this->route('kind')),
            $this->route('id') ? (int) $this->route('id') : null,
            $this->all(),
        )];
    }

    public function attributes(): array
    {
        return [
            'contact_id'          => 'customer',
            'location_id'         => 'location',
            'document_date'       => 'date',
            'lines.*.name'        => 'description',
            'lines.*.quantity'    => 'quantity',
            'lines.*.unit_price'  => 'price',
            'lines.*.discount_value' => 'discount',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required'       => 'Choose the customer.',
            'contact_id.exists'         => 'Choose one of this business\'s contacts.',
            'currency.in'               => 'Use the base currency or one switched on under Settings > Currencies.',
            'exchange_rate.required'    => 'A document in another currency needs its exchange rate.',
            'expiry_date.required'      => 'Choose until when it is valid.',
            'due_date.after_or_equal'   => 'The due date cannot come before the date.',
            'expiry_date.after_or_equal' => 'The expiry date cannot come before the date.',
            'lines.required'            => 'Add at least one line.',
            'lines.min'                 => 'Add at least one line.',
            'lines.*.name.required'     => 'Choose an item or describe the line.',
            'lines.*.quantity.gt'       => 'The quantity must be more than zero.',
            'lines.*.unit_price.min'    => 'A price cannot be negative. Returns and reductions are credit notes.',
            'decimal'                   => 'The :attribute has too many decimal places.',
        ];
    }
}
