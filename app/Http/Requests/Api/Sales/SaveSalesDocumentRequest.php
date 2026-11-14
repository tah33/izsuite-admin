<?php

namespace App\Http\Requests\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\Edition;
use App\Models\User\SalesDocument;
use App\Repositories\User\SalesTeamRepository;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Countries;
use Illuminate\Support\Arr;
use App\Http\Requests\Api\Concerns\RoundsExchangeRates;
use App\Models\User\Contact;
use App\Models\User\Location;
use App\Enums\SalesDocumentStatus;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\User\PriceList;
use App\Models\User\PriceListRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSalesDocumentRequest extends FormRequest
{
    use RoundsExchangeRates;

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

        $this->roundRate('exchange_rate');

        if (is_array($this->input('lines')) && ! $workspace?->usesTaxes()) {
            $this->merge(['lines' => array_map(fn ($line) => is_array($line) ? [...$line, 'tax_rate_id' => null] : $line, $this->input('lines'))]);
        }

        // Only the full Sales app keeps these (Doc 16 Phase A): from Sales Lite's form they are left as they are.
        if (! $workspace || ! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
            $this->replace(Arr::except($this->all(), SalesDocument::FULL_FIELDS));
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
     * when it is issued or sent - but what it does fill in must be valid. A
     * currency other than the base comes with its rate even on a draft: kept
     * without one, it would be issued at 1 whenever the business has no rate
     * entered for its date.
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
            'exchange_rate'          => [Rule::requiredIf($foreign), 'nullable', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'customer_reference'     => ['nullable', 'string', 'max:100'],
            'sales_reference'        => ['nullable', 'string', 'max:100'],
            'discount_type'          => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value'         => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'internal_notes'         => ['nullable', 'string', 'max:5000'],
            'terms'                  => ['nullable', 'string', 'max:10000'],
            'payment_instructions'   => ['nullable', 'string', 'max:5000'],
            // An invoice made from a sales order, billing beyond what it has left: why (Doc 16 Phase B2).
            'over_invoice_reason'    => ['sometimes', 'nullable', 'string', 'max:500'],
            // The full Sales app's (Doc 16 Phase A); sent only from its form.
            'salesperson_id'         => ['sometimes', 'nullable', 'integer'],
            'sales_team_id'          => ['sometimes', 'nullable', 'integer'],
            'expected_delivery_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'shipping_method'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'shipping_terms'         => ['sometimes', 'nullable', 'string', 'max:100'],
            ...$this->addressRules('billing_address'),
            ...$this->addressRules('shipping_address'),
            'lines'                  => $draft ? ['nullable', 'array', 'max:200'] : ['required', 'array', 'min:1', 'max:200'],
            // A sales order's line as it was, changed in place (Doc 16 Phase B): one of another document is a new line.
            'lines.*.id'             => ['nullable', 'integer'],
            // The line of its source a line was made from, kept through edits - an invoice's sales order line (Phase B2).
            'lines.*.source_line_id' => ['nullable', 'integer'],
            // A deduction of a deposit invoice (Doc 16 Phase D2): the one line whose price is negative.
            'lines.*.deposit_invoice_id' => ['nullable', 'integer'],
            // Why the discount goes past what the user's role gives (Doc 16 Phase F2).
            'discount_reason'            => ['nullable', 'string', 'max:500'],
            // The price list and rule that priced it (Doc 16 Phase F1): the business's own.
            'lines.*.price_list_id'      => ['nullable', 'integer', Rule::exists(PriceList::class, 'id')->where('workspace_id', $workspace?->id)],
            'lines.*.price_list_rule_id' => ['nullable', 'integer', Rule::exists(PriceListRule::class, 'id')->whereIn('price_list_id', PriceList::query()->where('workspace_id', $workspace?->id)->pluck('id')->all())],
            'lines.*.item_id'        => ['nullable', 'integer'],
            'lines.*.name'           => [$must(), 'string', 'max:255'],
            'lines.*.description'    => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity'       => [$must(), 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'lines.*.unit'           => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price'     => [$must(), 'numeric', 'decimal:0,2', 'min:-9999999999999.99', 'max:9999999999999.99', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_numeric($value) && $value < 0 && ! $this->input(str_replace('unit_price', 'deposit_invoice_id', $attribute))) {
                    $fail('A price cannot be negative. Returns and reductions are credit notes.');
                }
            }],
            'lines.*.discount_type'  => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'lines.*.tax_rate_id'    => ['nullable', 'integer'],
        ];
    }

    /** A document's own address, or none: the customer's. */
    private function addressRules(string $field): array
    {
        return [
            $field                  => ['sometimes', 'nullable', 'array:'.implode(',', SalesDocument::ADDRESS_PARTS)],
            "{$field}.line1"        => ['nullable', 'string', 'max:255'],
            "{$field}.line2"        => ['nullable', 'string', 'max:255'],
            "{$field}.city"         => ['nullable', 'string', 'max:100'],
            "{$field}.state"        => ['nullable', 'string', 'max:100'],
            "{$field}.postal_code"  => ['nullable', 'string', 'max:20'],
            "{$field}.country"      => ['nullable', Rule::in(Countries::codes())],
        ];
    }

    /** What needs the database or the settings - see SalesDocumentChecks - and the salesperson and team being the business's own. */
    public function after(): array
    {
        return [
            fn (Validator $validator) => app(SalesDocumentChecks::class)->check(
                $validator,
                $this->attributes->get('workspace'),
                DocumentType::fromKind((string) $this->route('kind')),
                $this->route('id') ? (int) $this->route('id') : null,
                $this->all(),
            ),
            function (Validator $validator) {
                $workspace = $this->attributes->get('workspace');
                $teams     = app(SalesTeamRepository::class);

                if ($this->filled('salesperson_id') && ! $teams->isMember($workspace, (int) $this->input('salesperson_id'))) {
                    $validator->errors()->add('salesperson_id', 'Choose a member of this business.');
                }

                if ($this->filled('sales_team_id') && ! $teams->find($workspace, (int) $this->input('sales_team_id'))) {
                    $validator->errors()->add('sales_team_id', 'Choose one of the business\'s sales teams.');
                }
            },
        ];
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
