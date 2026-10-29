<?php

namespace App\Http\Requests\Api\Purchases;

use App\Models\User\Contact;
use App\Models\User\Location;
use App\Repositories\User\LocationRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ISO currency codes are upper case. A purchase in the base currency has
     * an exchange rate of 1. While the business does not use taxes, lines
     * carry none (Doc 09 section 14).
     */
    protected function prepareForValidation(): void
    {
        $workspace = $this->attributes->get('workspace');

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

    /**
     * The same for adding and for editing a draft (Doc 09 section 6). A
     * supplier, the date, the currency, the location where the business has
     * one, and at least one line are required; the due date may be left to
     * the payment terms. A currency other than the base needs its exchange
     * rate. The supplier's own invoice number and date, and a reference, are
     * optional.
     *
     * Each line is an item or a description, more than zero of it, at a price
     * that is never negative.
     */
    public function rules(): array
    {
        $workspace  = $this->attributes->get('workspace');
        $inBusiness = fn (string $model) => Rule::exists($model, 'id')->where('workspace_id', $workspace?->id);
        $foreign    = $this->input('currency') !== $workspace?->base_currency;

        return [
            'contact_id'              => ['required', 'integer', $inBusiness(Contact::class)],
            'location_id'             => [$workspace && app(LocationRepository::class)->existsFor($workspace) ? 'required' : 'nullable', 'nullable', 'integer', $inBusiness(Location::class)],
            'document_date'           => ['required', 'date_format:Y-m-d'],
            'due_date'                => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'payment_terms'           => ['nullable', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS],
            'currency'                => ['required', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
            'exchange_rate'           => [Rule::requiredIf($foreign), 'nullable', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date'   => ['nullable', 'date_format:Y-m-d'],
            'reference'               => ['nullable', 'string', 'max:100'],
            'discount_type'           => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value'          => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'notes'                   => ['nullable', 'string', 'max:5000'],
            'lines'                   => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id'         => ['nullable', 'integer'],
            'lines.*.name'            => ['required', 'string', 'max:255'],
            'lines.*.description'     => ['nullable', 'string', 'max:5000'],
            'lines.*.quantity'        => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'lines.*.unit'            => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price'      => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'lines.*.discount_type'   => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value'  => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'lines.*.tax_rate_id'     => ['nullable', 'integer'],
        ];
    }

    /** What needs the database - see PurchaseChecks. */
    public function after(): array
    {
        return [fn (Validator $validator) => app(PurchaseChecks::class)->check(
            $validator,
            $this->attributes->get('workspace'),
            $this->route('id') ? (int) $this->route('id') : null,
            $this->all(),
        )];
    }

    public function attributes(): array
    {
        return [
            'contact_id'              => 'supplier',
            'location_id'             => 'location',
            'document_date'           => 'date',
            'supplier_invoice_number' => 'supplier invoice number',
            'supplier_invoice_date'   => 'supplier invoice date',
            'lines.*.name'            => 'description',
            'lines.*.quantity'        => 'quantity',
            'lines.*.unit_price'      => 'price',
            'lines.*.discount_value'  => 'discount',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required'     => 'Choose the supplier.',
            'contact_id.exists'       => 'Choose one of this business\'s contacts.',
            'currency.in'             => 'Use the base currency or one switched on under Settings > Currencies.',
            'exchange_rate.required'  => 'A purchase in another currency needs its exchange rate.',
            'due_date.after_or_equal' => 'The due date cannot come before the date.',
            'lines.required'          => 'Add at least one line.',
            'lines.*.quantity.gt'     => 'The quantity must be more than zero.',
            'lines.*.unit_price.min'  => 'A price cannot be negative.',
        ];
    }
}
