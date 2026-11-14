<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\Package;
use App\Repositories\Billing\PackageRepository;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePackageRequest extends FormRequest
{
    /** The cycles' switches: the form sends each as 0 or 1, so one left out stays as it is. */
    private const CYCLES = ['offers_monthly', 'offers_yearly'];

    private ?Package $package = null;

    public function authorize(): bool
    {
        return true;
    }

    /** An unticked box sends nothing, so it is read as false; the currency is kept in capitals - the plan's own when none is sent. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active'        => $this->boolean('is_active'),
            'removes_branding' => $this->boolean('removes_branding'),
            'price_currency'   => strtoupper(trim((string) $this->input('price_currency', $this->currentCurrency()))) ?: $this->currentCurrency(),
            ...collect(self::CYCLES)->filter(fn (string $field) => $this->has($field))->mapWithKeys(fn (string $field) => [$field => $this->boolean($field)])->all(),
        ]);
    }

    /**
     * The storage limit is in MB; the others are counts. Left empty, a limit
     * is no limit. A business already over a lower one keeps what it has - it
     * only cannot add more (Doc 13 section 13.14).
     *
     * The currency is the plan's main one, for a business whose own it has no
     * price in; the prices themselves are on the plan's prices card (Doc 15
     * Phase C3a). Trial and grace are days - empty, none.
     *
     * Its reporting and support levels - empty, not shown - the cycles it is
     * offered on, at least one, and the billing countries it is offered in -
     * none chosen, every one (Phase E3a).
     */
    public function rules(): array
    {
        $count = ['nullable', 'integer', 'min:1', 'max:100000'];

        return [
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'business_limit'   => $count,
            'user_limit'       => $count,
            'location_limit'   => $count,
            // The plan keeps its own currency whatever the list holds; another must be one of the admin's (Admin > Currencies).
            'price_currency'   => ['required', 'string', 'size:3', 'alpha', Rule::when($this->input('price_currency') !== $this->currentCurrency(), [Rule::exists('currencies', 'code')])],
            'trial_days'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'grace_days'       => ['nullable', 'integer', 'min:1', 'max:90'],
            'removes_branding' => ['boolean'],
            'reporting_level'  => ['nullable', Rule::in(array_keys(Package::REPORTING_LEVELS))],
            'support_level'    => ['nullable', Rule::in(array_keys(Package::SUPPORT_LEVELS))],
            'offers_monthly'   => ['sometimes', 'boolean'],
            'offers_yearly'    => ['sometimes', 'boolean'],
            'countries'        => ['nullable', 'array'],
            'countries.*'      => [Rule::in(Countries::codes())],
            'is_active'        => ['boolean'],
            // The day a change to the limits, branding, levels, trial or grace starts, as the plan's next version (Phase E3b) - empty, today.
            'effective_on'     => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /** A paid plan stays offered on one cycle at least - each as sent, or as it is. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $offered = collect(self::CYCLES)->contains(fn (string $field) => (bool) $this->input($field, $this->package()->{$field} ?? true));

                if (! $this->package()->isFree() && ! $offered) {
                    $validator->errors()->add('offers_monthly', 'Offer the plan monthly, yearly or both.');
                }
            },
        ];
    }

    /** The plan being edited. */
    private function package(): Package
    {
        return $this->package ??= app(PackageRepository::class)->findOrFail((int) $this->route('id'));
    }

    /** The plan's currency now. */
    private function currentCurrency(): string
    {
        return $this->package()->price_currency;
    }

    public function messages(): array
    {
        $count    = 'Enter a whole number of at least 1, or leave it empty for no limit.';
        $messages = [
            'storage_limit_mb.integer' => 'Enter the storage limit as a whole number of MB, or leave it empty for no limit.',
            'storage_limit_mb.min'     => 'Enter the storage limit as a whole number of MB, or leave it empty for no limit.',
        ];

        foreach (['business_limit', 'user_limit', 'location_limit'] as $field) {
            foreach (['integer', 'min', 'max'] as $rule) {
                $messages["{$field}.{$rule}"] = $count;
            }
        }

        $messages['effective_on.after_or_equal'] = 'Choose today or a later day.';

        return $messages;
    }
}
