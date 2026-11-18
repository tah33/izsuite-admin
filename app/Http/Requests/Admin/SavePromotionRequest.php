<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\Promotion;
use App\Models\Billing\WorkspaceSubscription;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A promotion code's fields (Doc 15 section 15.17 - Phase C3b): its name and
 * code - kept in capitals - the discount, a fixed one's currency, the plans,
 * cycles and countries it holds for (none ticked, all), its days, limits,
 * new customers only, and whether it is active.
 */
class SavePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'               => strtoupper(trim((string) $this->input('code'))),
            'currency'           => $this->filled('currency') ? strtoupper((string) $this->input('currency')) : null,
            'new_customers_only' => $this->boolean('new_customers_only'),
            'per_business'       => $this->input('per_business', 1),
        ]);
    }

    public function rules(): array
    {
        $percent = $this->input('discount_type') === Promotion::PERCENT;

        return [
            'name'               => ['required', 'string', 'max:100'],
            'code'               => ['required', 'string', 'regex:/^[A-Z0-9_-]{3,40}$/', Rule::unique('promotions', 'code')->ignore($this->route('id'))],
            'discount_type'      => ['required', Rule::in([Promotion::PERCENT, Promotion::FIXED])],
            'discount_value'     => ['required', 'numeric', 'gt:0', 'decimal:0,2', $percent ? 'max:100' : 'max:99999999'],
            'currency'           => ['nullable', Rule::requiredIf(! $percent), 'string', 'size:3', Rule::exists('currencies', 'code')],
            'packages'           => ['nullable', 'array'],
            'packages.*'         => ['string', Rule::exists('packages', 'key')->where(fn ($query) => $query->where('key', '!=', 'starter'))],
            'cycles'             => ['nullable', 'array'],
            'cycles.*'           => [Rule::in(WorkspaceSubscription::CYCLES)],
            'countries'          => ['nullable', 'array'],
            'countries.*'        => [Rule::in(Countries::codes())],
            'starts_on'          => ['nullable', 'date'],
            'ends_on'            => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_redemptions'    => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_business'       => ['required', 'integer', 'min:1', 'max:1000'],
            'new_customers_only' => ['boolean'],
            'status'             => ['required', Rule::in([Promotion::ACTIVE, Promotion::PAUSED])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'           => 'Use 3 to 40 letters, numbers, - or _.',
            'code.unique'          => 'Another promotion has this code.',
            'currency.required'    => 'A fixed amount needs its currency: it comes off invoices in it only.',
            'discount_value.max'   => $this->input('discount_type') === Promotion::PERCENT ? 'A percent is 100 at most.' : 'That amount is too large.',
            'ends_on.after_or_equal' => 'The last day cannot come before the first.',
        ];
    }
}
