<?php

namespace App\Http\Requests\Api\Expenses;

use App\Http\Requests\Api\Concerns\PicksPaymentAccount;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use App\Models\User\WithholdingTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExpensePaymentRequest extends FormRequest
{
    use PicksPaymentAccount;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * An expense payment (Doc 10 section 12): its date, the active account it
     * came out of - one that pays suppliers and expenses (Doc 05 section 6) -
     * and the amount paid are required. applied_amount is what it pays off the
     * expense when the account's currency is not the expense's (section 13) -
     * ExpensePaymentService asks for it then. The withholding tax, when some
     * was kept back from the payee, is a rate of the business and the amount
     * kept. The methods are a customer payment's.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'payment_date'       => ['required', 'date_format:Y-m-d'],
            'payment_account_id' => ['required', 'integer', Rule::exists(PaymentAccount::class, 'id')->where('workspace_id', $workspace?->id)->where('is_active', true)->where('allow_supplier_payments', true)->where($this->usableAccount())],
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
            'payment_account_id.exists'        => 'Choose one of your active payment accounts that pays suppliers and expenses.',
            'method.in'                        => 'Choose how it was paid.',
            'withholding_tax_id.exists'        => 'Choose one of your withholding taxes.',
            'withholding_amount.required_with' => 'Enter the amount withheld.',
            'decimal'                          => 'The :attribute can have at most two decimal places.',
            'gt'                               => 'The :attribute must be more than zero.',
        ];
    }
}
