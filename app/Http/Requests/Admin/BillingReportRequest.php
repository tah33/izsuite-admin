<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Admin > Billing Reports' span of months, currency and plan (Doc 15 section 14). */
class BillingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'     => ['nullable', 'date_format:Y-m'],
            'to'       => ['nullable', 'date_format:Y-m', 'after_or_equal:from'],
            'currency' => ['nullable', 'string', 'size:3'],
            'plan'     => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The last month cannot come before the first.',
        ];
    }
}
