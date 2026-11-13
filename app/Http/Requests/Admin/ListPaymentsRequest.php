<?php

namespace App\Http\Requests\Admin;

use App\Repositories\Admin\PaymentAdminRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Payments' filters (Doc 15 section 14): status - the failed ones among them - how it was paid, and words. */
class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(PaymentAdminRepository::STATUSES)],
            'method' => ['nullable', Rule::in(['card', 'transfer'])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
