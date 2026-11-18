<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\SubscriptionInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The subscription invoices list's filters. Rows per page is read by requested_per_page(). */
class ListSubscriptionInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([SubscriptionInvoice::OPEN, SubscriptionInvoice::PAID, SubscriptionInvoice::VOID])],
        ];
    }
}
