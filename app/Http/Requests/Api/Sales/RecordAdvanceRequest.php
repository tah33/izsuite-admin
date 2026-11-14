<?php

namespace App\Http\Requests\Api\Sales;

use App\Http\Requests\Api\Concerns\PicksPaymentAccount;
use App\Models\User\Contact;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAdvanceRequest extends FormRequest
{
    use PicksPaymentAccount;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * A customer advance (Doc 16 section 16.15): the customer - or the sales
     * order it was paid for, which gives the customer and the currency - the
     * payment date, the active account it went into, the currency and the
     * amount. account_amount is what reached the account when its currency is
     * not the advance's: CustomerAdvanceService asks for it then.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'contact_id'         => ['required_without:sales_order_id', 'nullable', 'integer', Rule::exists(Contact::class, 'id')->where('workspace_id', $workspace?->id)],
            'sales_order_id'     => ['nullable', 'integer'],
            'payment_date'       => ['required', 'date_format:Y-m-d'],
            'payment_account_id' => ['required', 'integer', Rule::exists(PaymentAccount::class, 'id')->where('workspace_id', $workspace?->id)->where('is_active', true)->where('allow_customer_payments', true)->where($this->usableAccount())],
            'currency'           => ['required_without:sales_order_id', 'nullable', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
            'amount'             => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'account_amount'     => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'method'             => ['nullable', Rule::in(SalesPayment::METHODS)],
            'reference'          => ['nullable', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contact_id'         => 'customer',
            'payment_date'       => 'date',
            'payment_account_id' => 'account',
            'account_amount'     => 'amount into the account',
        ];
    }
}
