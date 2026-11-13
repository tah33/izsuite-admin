<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\SubscriptionRefund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Refunds' filters (Doc 15 section 14): where a refund stands, and words. */
class ListRefundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([SubscriptionRefund::PENDING, SubscriptionRefund::SUCCEEDED, SubscriptionRefund::FAILED])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
