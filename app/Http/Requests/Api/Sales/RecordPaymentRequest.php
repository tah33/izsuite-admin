<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use App\Models\User\WithholdingTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A customer payment (Doc 08 section 13): its date, the active account it
     * went into and the amount received are required. applied_amount is what
     * it pays off the invoice when the account's currency is not the
     * invoice's (section 14) - SalesPaymentService asks for it then. The
     * withholding tax, when the customer kept some back, is a rate of the
     * business and the amount kept.
     *
     * The refund form sends the same fields, without the withholding tax.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'payment_date'       => ['required', 'date_format:Y-m-d'],
            'payment_account_id' => ['required', 'integer', Rule::exists(PaymentAccount::class, 'id')->where('workspace_id', $workspace?->id)->where('is_active', true)],
            'amount'             => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'applied_amount'     => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'method'             => ['nullable', Rule::in(SalesPayment::METHODS)],
            'reference'          => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'withholding_tax_id' => ['nullable', 'integer', Rule::exists(WithholdingTax::class, 'id')->where('workspace_id', $workspace?->id)],
            'withholding_amount' => ['nullable', 'required_with:withholding_tax_id', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_date'       => 'date',
            'payment_account_id' => 'account',
            'applied_amount'     => 'amount applied',
            'withholding_amount' => 'amount withheld',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_account_id.exists'        => 'Choose one of your active payment accounts.',
            'method.in'                        => 'Choose how it was paid.',
            'withholding_tax_id.exists'        => 'Choose one of your withholding taxes.',
            'withholding_amount.required_with' => 'Enter the amount withheld.',
            'decimal'                          => 'The :attribute can have at most two decimal places.',
            'gt'                               => 'The :attribute must be more than zero.',
        ];
    }
}
