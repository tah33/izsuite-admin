<?php

namespace App\Http\Requests\Admin;

use App\Repositories\Billing\PackageRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
{
    private ?string $currentCurrency = null;

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
        ]);
    }

    /**
     * The storage limit is in MB; the others are counts. Left empty, a limit
     * is no limit. A business already over a lower one keeps what it has - it
     * only cannot add more (Doc 13 section 13.14).
     *
     * Prices are in the plan's currency; left empty, the price is not yet
     * announced (section 2). Trial and grace are days - empty, none.
     */
    public function rules(): array
    {
        $count = ['nullable', 'integer', 'min:1', 'max:100000'];
        $price = ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'];

        return [
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'business_limit'   => $count,
            'user_limit'       => $count,
            'location_limit'   => $count,
            // The plan keeps its own currency whatever the list holds; another must be one of the admin's (Admin > Currencies).
            'price_currency'   => ['required', 'string', 'size:3', 'alpha', Rule::when($this->input('price_currency') !== $this->currentCurrency(), [Rule::exists('currencies', 'code')])],
            'price_monthly'    => $price,
            'price_yearly'     => $price,
            'trial_days'       => ['nullable', 'integer', 'min:1', 'max:365'],
            'grace_days'       => ['nullable', 'integer', 'min:1', 'max:90'],
            'removes_branding' => ['boolean'],
            'is_active'        => ['boolean'],
        ];
    }

    /** The plan's currency now. */
    private function currentCurrency(): string
    {
        return $this->currentCurrency ??= app(PackageRepository::class)->findOrFail((int) $this->route('id'))->price_currency;
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

        return $messages;
    }
}
