<?php

namespace App\Http\Requests\Api\Expenses;

use App\Http\Requests\Api\Concerns\RoundsExchangeRates;
use App\Models\User\Contact;
use App\Models\User\Location;
use App\Repositories\User\LocationRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveExpenseRequest extends FormRequest
{
    use RoundsExchangeRates;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * ISO currency codes are upper case. An expense in the base currency has
     * an exchange rate of 1. While the business does not use taxes, lines
     * carry none (Doc 10 section 14).
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

        $this->roundRate('exchange_rate');

        if (is_array($this->input('lines')) && ! $workspace?->usesTaxes()) {
            $this->merge(['lines' => array_map(fn ($line) => is_array($line) ? [...$line, 'tax_rate_id' => null] : $line, $this->input('lines'))]);
        }
    }

    /**
     * The same for adding and for editing a draft (Doc 10 section 5). A payee
     * - one of the business's contacts, or a name typed in - the date, the
     * currency, the location where the business has one, and at least one line
     * are required; the due date may be left to the payment terms. A currency
     * other than the base needs its exchange rate. The receipt or invoice
     * number and a reference are optional.
     *
     * Each line has a category, and a quantity and unit amount of more than
     * zero: an expense is never negative.
     */
    public function rules(): array
    {
        $workspace  = $this->attributes->get('workspace');
        $inBusiness = fn (string $model) => Rule::exists($model, 'id')->where('workspace_id', $workspace?->id);
        $foreign    = $this->input('currency') !== $workspace?->base_currency;

        return [
            'contact_id'                  => ['nullable', 'required_without:payee_name', 'integer', $inBusiness(Contact::class)],
            'payee_name'                  => ['nullable', 'required_without:contact_id', 'string', 'max:255'],
            'location_id'                 => [$workspace && app(LocationRepository::class)->existsFor($workspace) ? 'required' : 'nullable', 'nullable', 'integer', $inBusiness(Location::class)],
            'document_date'               => ['required', 'date_format:Y-m-d'],
            'due_date'                    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:document_date'],
            'payment_terms'               => ['nullable', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS],
            'currency'                    => ['required', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
            'exchange_rate'               => [Rule::requiredIf($foreign), 'nullable', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'receipt_number'              => ['nullable', 'string', 'max:100'],
            'reference'                   => ['nullable', 'string', 'max:100'],
            'notes'                       => ['nullable', 'string', 'max:5000'],
            'lines'                       => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.expense_category_id' => ['required', 'integer'],
            'lines.*.description'         => ['nullable', 'string', 'max:1000'],
            'lines.*.quantity'            => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'lines.*.unit_price'          => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'lines.*.tax_rate_id'         => ['nullable', 'integer'],
        ];
    }

    /** What needs the database - see ExpenseChecks. */
    public function after(): array
    {
        return [fn (Validator $validator) => app(ExpenseChecks::class)->check(
            $validator,
            $this->attributes->get('workspace'),
            $this->route('id') ? (int) $this->route('id') : null,
            $this->all(),
        )];
    }

    public function attributes(): array
    {
        return [
            'contact_id'          => 'payee',
            'payee_name'          => 'payee',
            'location_id'         => 'location',
            'document_date'       => 'date',
            'receipt_number'      => 'receipt number',
            'lines.*.description' => 'description',
            'lines.*.quantity'    => 'quantity',
            'lines.*.unit_price'  => 'amount',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required_without'          => 'Choose the payee, or type their name.',
            'payee_name.required_without'          => 'Choose the payee, or type their name.',
            'contact_id.exists'                    => 'Choose one of this business\'s contacts.',
            'currency.in'                          => 'Use the base currency or one switched on under Settings > Currencies.',
            'exchange_rate.required'               => 'An expense in another currency needs its exchange rate.',
            'due_date.after_or_equal'              => 'The due date cannot come before the date.',
            'lines.required'                       => 'Add at least one line.',
            'lines.*.expense_category_id.required' => 'Choose a category.',
            'lines.*.quantity.gt'                  => 'The quantity must be more than zero.',
            'lines.*.unit_price.gt'                => 'The amount must be more than zero.',
        ];
    }
}
