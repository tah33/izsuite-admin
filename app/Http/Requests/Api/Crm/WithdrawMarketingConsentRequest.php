<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A consent given withdrawn (Doc 17 Phase I3): when - the business's day and
 * time, now when left out - and a note on how, such as "replied STOP".
 */
class WithdrawMarketingConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'withdrawn_on'   => ['nullable', 'required_with:withdrawn_time', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'withdrawn_time' => ['nullable', 'date_format:H:i'],
            'note'           => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'withdrawn_on.required_with' => 'Choose the day with the time.',
        ];
    }
}
