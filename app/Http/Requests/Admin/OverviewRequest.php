<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\BusinessService;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin > Overview's filters (Doc 15 section 15.4 - Phase D1): the days, and
 * the businesses' plan (key), country, subscription and app (ID), with the
 * billing's currency.
 */
class OverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    public function rules(): array
    {
        return [
            'from'         => ['nullable', 'date_format:Y-m-d'],
            'to'           => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'package'      => ['nullable', 'string', Rule::exists('packages', 'key')],
            'country'      => ['nullable', 'string', Rule::in(Countries::codes())],
            'subscription' => ['nullable', Rule::in(array_keys(BusinessService::SUBSCRIPTION_LABELS))],
            'app'          => ['nullable', 'integer', Rule::exists('apps', 'id')],
            'currency'     => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
        ];
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The last day cannot come before the first.',
        ];
    }
}
