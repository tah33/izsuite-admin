<?php

namespace App\Http\Requests\Api\PaymentAccounts;

use App\Models\User\AccountTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAccountTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A payment account's history filtered by type and a range of dates - on the page and in its export. */
    public function rules(): array
    {
        return [
            'type'      => ['nullable', Rule::in(array_keys(AccountTransaction::TYPES))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
