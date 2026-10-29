<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A customer refund (Doc 08 section 16): its date, the active account it
     * is paid from - one that takes customer payments, refunds being customer
     * money too (Doc 05 section 6) - and the amount are required.
     * applied_amount is what it takes off the credit note when the account's
     * currency is not the credit note's - CreditNoteRefunds asks for it then.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'payment_date'       => ['required', 'date_format:Y-m-d'],
            'payment_account_id' => ['required', 'integer', Rule::exists(PaymentAccount::class, 'id')->where('workspace_id', $workspace?->id)->where('is_active', true)->where('allow_customer_payments', true)],
            'amount'             => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'applied_amount'     => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'method'             => ['nullable', Rule::in(SalesPayment::METHODS)],
            'reference'          => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_date'       => 'date',
            'payment_account_id' => 'account',
            'applied_amount'     => 'amount refunded from the credit note',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_account_id.exists' => 'Choose one of your active payment accounts that takes customer payments.',
            'method.in'                 => 'Choose how it was paid back.',
            'decimal'                   => 'The :attribute can have at most two decimal places.',
            'gt'                        => 'The :attribute must be more than zero.',
        ];
    }
}
